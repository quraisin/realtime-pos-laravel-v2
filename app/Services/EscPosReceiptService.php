<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Models\ReceiptPrintLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EscPosReceiptService
{
    // ESC/POS Command Constants
    public const ESC = "\x1B";
    public const GS  = "\x1D";
    public const LF  = "\x0A";

    public const CMD_INIT         = "\x1B\x40";           // ESC @
    public const CMD_ALIGN_LEFT   = "\x1B\x61\x00";       // ESC a 0
    public const CMD_ALIGN_CENTER = "\x1B\x61\x01";       // ESC a 1
    public const CMD_ALIGN_RIGHT  = "\x1B\x61\x02";       // ESC a 2
    public const CMD_BOLD_ON      = "\x1B\x45\x01";       // ESC E 1
    public const CMD_BOLD_OFF     = "\x1B\x45\x00";       // ESC E 0
    public const CMD_TEXT_NORMAL  = "\x1D\x21\x00";       // GS ! 0
    public const CMD_TEXT_DOUBLE  = "\x1D\x21\x11";       // GS ! 0x11 (Double height & width)
    public const CMD_TEXT_BIG_H   = "\x1D\x21\x01";       // Double height only
    public const CMD_CUT_PAPER    = "\x1D\x56\x00";       // GS V 0 (Cut paper with 0 feed)
    public const CMD_DRAWER_KICK  = "\x1B\x70\x00\x19\xFA"; // ESC p 0 25 250 (Cash drawer pulse)

    /**
     * Get maximum character width based on paper size
     */
    public static function getMaxWidth(string $paperSize = '58mm'): int
    {
        return strtolower($paperSize) === '80mm' ? 48 : 32;
    }

    /**
     * Get list of installed Windows printers
     */
    public function getInstalledWindowsPrinters(): array
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            return [];
        }

        try {
            $cmd = 'powershell -NoProfile -Command "Get-Printer | Select-Object -ExpandProperty Name"';
            $output = shell_exec($cmd);
            if ($output) {
                $lines = array_map('trim', explode("\n", $output));
                return array_values(array_filter($lines, fn($line) => !empty($line)));
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return ['Putian POS Thermal 2'];
    }

    /**
     * Send raw ESC/POS bytes directly to Windows Thermal Printer
     */
    public function printDirectToWindowsPrinter(string $rawBytes, ?string $printerName = null): array
    {
        $settings = PrinterSetting::instance();
        $targetPrinter = $printerName ?: ($settings->windows_printer_name ?: 'Putian POS Thermal 2');

        $tempFile = storage_path('app/temp_print_' . uniqid() . '.bin');
        if (!is_dir(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0777, true);
        }
        file_put_contents($tempFile, $rawBytes);

        $scriptPath = base_path('scripts/raw_print.ps1');

        $cmd = sprintf(
            'powershell -NoProfile -ExecutionPolicy Bypass -File %s -FilePath %s -PrinterName %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($tempFile),
            escapeshellarg($targetPrinter)
        );

        $output = shell_exec($cmd);
        @unlink($tempFile);

        $isSuccess = str_contains((string)$output, 'SUCCESS');

        return [
            'success' => $isSuccess,
            'printer' => $targetPrinter,
            'output' => trim((string)$output),
            'message' => $isSuccess 
                ? "Struk berhasil dicetak ke mesin printer: {$targetPrinter}" 
                : "Gagal mencetak ke {$targetPrinter}: {$output}",
        ];
    }

    /**
     * Record receipt print event, calculate latency from order created, update order, and write to Laravel log.
     */
    public function recordPrintLog(
        Order $order,
        string $method,
        array $printResult,
        ?float $orderStartMicrotime = null,
        ?int $durationMs = null,
        ?User $user = null
    ): ReceiptPrintLog {
        // Calculate latency in milliseconds from order created
        if ($orderStartMicrotime !== null) {
            $latencyMs = (int) max(1, round((microtime(true) - $orderStartMicrotime) * 1000));
        } else {
            $orderCreatedAt = $order->created_at ?? now();
            $latencyMs = (int) max(1, $orderCreatedAt->diffInMilliseconds(now()));
        }

        $isSuccess = (bool)($printResult['success'] ?? false);
        $printerName = $printResult['printer'] ?? null;
        $errorMessage = $isSuccess ? null : ($printResult['message'] ?? 'Unknown error');
        $userId = $user?->id ?? Auth::id() ?? $order->cashier_id;

        $printLog = ReceiptPrintLog::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'print_method' => $method,
            'printer_name' => $printerName,
            'order_created_at' => $order->created_at,
            'printed_at' => now(),
            'latency_ms' => $latencyMs,
            'duration_ms' => $durationMs,
            'status' => $isSuccess ? 'success' : 'failed',
            'error_message' => $errorMessage,
            'cashier_id' => $userId,
        ]);

        if ($isSuccess) {
            $order->update([
                'receipt_printed_at' => now(),
                'receipt_print_latency_ms' => $latencyMs,
                'receipt_print_method' => $method,
                'receipt_print_count' => ($order->receipt_print_count ?? 0) + 1,
            ]);

            $logMsg = sprintf(
                "[RECEIPT PRINTED] Order #%s | Latency from Order Created: %d ms (%.2f s) | Print Duration: %d ms | Method: %s | Printer: %s | Cashier: %s",
                $order->order_number,
                $latencyMs,
                $latencyMs / 1000,
                $durationMs ?? 0,
                $method,
                $printerName ?: 'Putian POS Thermal 2',
                $user?->name ?? $order->cashier?->name ?? 'Kasir'
            );
            Log::info($logMsg);
            \App\Services\FeatureLog::printer('RECEIPT_PRINTED', $logMsg, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'latency_ms' => $latencyMs,
                'duration_ms' => $durationMs,
                'method' => $method,
                'printer' => $printerName,
            ]);
        } else {
            $failMsg = sprintf(
                "[RECEIPT PRINT FAILED] Order #%s | Latency until failure: %d ms | Method: %s | Printer: %s | Error: %s",
                $order->order_number,
                $latencyMs,
                $method,
                $printerName ?: 'Putian POS Thermal 2',
                $errorMessage
            );
            Log::warning($failMsg);
            \App\Services\FeatureLog::printer('RECEIPT_PRINT_FAILED', $failMsg, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'latency_ms' => $latencyMs,
                'method' => $method,
                'printer' => $printerName,
                'error' => $errorMessage,
            ], 'warning');
        }

        return $printLog;
    }

    /**
     * Generate binary ESC/POS byte stream for Customer Receipt
     */
    public function generateReceiptBytes(Order $order, ?PrinterSetting $settings = null): string
    {
        $settings = $settings ?? PrinterSetting::instance();
        $width = self::getMaxWidth($settings->paper_size);

        $out = '';

        // Cash drawer kick if enabled
        if ($settings->open_cash_drawer && $order->payment_method === 'cash') {
            $out .= self::CMD_DRAWER_KICK;
        }

        // Initialize printer
        $out .= self::CMD_INIT;

        // Store Header (Center aligned)
        $out .= self::CMD_ALIGN_CENTER;
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_DOUBLE;
        $out .= $settings->store_name . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        if (!empty($settings->store_tagline)) {
            $out .= $settings->store_tagline . self::LF;
        }
        $out .= $settings->store_address . self::LF;
        $out .= 'Telp: ' . $settings->store_phone . self::LF;
        $out .= str_repeat('=', $width) . self::LF;

        // Order Meta (Left aligned)
        $out .= self::CMD_ALIGN_LEFT;
        $out .= 'No: ' . $order->order_number . self::LF;
        $out .= $this->formatTwoColumns(date('d/m/Y H:i', strtotime($order->created_at)), 'Tipe: ' . strtoupper(str_replace('_', ' ', $order->order_type)), $width) . self::LF;
        $out .= 'Kasir: ' . ($order->cashier ? $order->cashier->name : 'Kasir POS') . self::LF;
        
        if ($order->table_number) {
            $out .= 'Meja / Lokasi: ' . $order->table_number . self::LF;
        }
        $out .= 'Pelanggan: ' . $order->customer_name . self::LF;
        $out .= str_repeat('-', $width) . self::LF;

        // Items Header
        $out .= $this->formatItemsHeader($width) . self::LF;
        $out .= str_repeat('-', $width) . self::LF;

        // Items List
        foreach ($order->items as $item) {
            $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Item');
            $out .= $productName . self::LF;
            $qty = (int)($item->quantity ?? 1);
            $price = (float)($item->price ?? 0);
            $subtotal = (float)($item->subtotal ?? ($price * $qty));
            $qtyPrice = ' ' . $qty . ' x ' . number_format($price, 0, ',', '.');
            $subtotalStr = number_format($subtotal, 0, ',', '.');
            $out .= $this->formatTwoColumns($qtyPrice, $subtotalStr, $width) . self::LF;

            if (!empty($item->notes)) {
                $out .= '   * ' . $item->notes . self::LF;
            }
        }

        $out .= str_repeat('-', $width) . self::LF;

        // Subtotals & Payments
        $out .= $this->formatTwoColumns('Subtotal', 'Rp ' . number_format($order->subtotal, 0, ',', '.'), $width) . self::LF;
        
        if ($order->discount_amount > 0) {
            $out .= $this->formatTwoColumns('Diskon', '-Rp ' . number_format($order->discount_amount, 0, ',', '.'), $width) . self::LF;
        }

        if ($order->tax_amount > 0) {
            $out .= $this->formatTwoColumns('Pajak (PB1 ' . (int)$order->tax_rate . '%)', 'Rp ' . number_format($order->tax_amount, 0, ',', '.'), $width) . self::LF;
        }

        $out .= str_repeat('=', $width) . self::LF;
        $out .= self::CMD_BOLD_ON;
        $out .= $this->formatTwoColumns('TOTAL', 'Rp ' . number_format($order->total_amount, 0, ',', '.'), $width) . self::LF;
        $out .= self::CMD_BOLD_OFF;

        $out .= $this->formatTwoColumns('Metode Bayar', strtoupper(str_replace('_', ' ', $order->payment_method)), $width) . self::LF;
        if ($order->payment_method === 'cash') {
            $out .= $this->formatTwoColumns('Tunai', 'Rp ' . number_format($order->cash_tendered, 0, ',', '.'), $width) . self::LF;
            $out .= $this->formatTwoColumns('Kembalian', 'Rp ' . number_format($order->change_due, 0, ',', '.'), $width) . self::LF;
        }
        $statusLabel = match(strtolower((string)$order->payment_status)) {
            'paid' => 'LUNAS (PAID)',
            'void' => 'BATAL (VOID)',
            'refunded' => 'REFUND',
            default => strtoupper((string)$order->payment_status),
        };
        $out .= $this->formatTwoColumns('Status', $statusLabel, $width) . self::LF;

        if (!empty($order->notes)) {
            $out .= str_repeat('-', $width) . self::LF;
            $out .= 'CATATAN PESANAN: ' . $order->notes . self::LF;
        }

        $out .= str_repeat('=', $width) . self::LF;

        // Footer Message
        $out .= self::CMD_ALIGN_CENTER;
        $footerLines = explode("\n", str_replace("\r", '', $settings->receipt_footer));
        foreach ($footerLines as $line) {
            $out .= trim($line) . self::LF;
        }
        $out .= 'Simpan struk ini sebagai bukti.' . self::LF;
        $out .= 'POS System Powered by Qurais Kitchen' . self::LF;

        // Cut Paper: Only send to 80mm printers with auto-cutter mechanism.
        // 58mm printers (such as Putian POS-58) use manual serrated tear bars;
        // sending GS V cut commands triggers their firmware to feed 35-45mm of blank paper.
        if ($settings->cut_paper && strtolower($settings->paper_size) === '80mm') {
            $out .= self::CMD_CUT_PAPER;
        }

        return $out;
    }

    /**
     * Generate binary ESC/POS byte stream for Kitchen Order Slip (KDS / Dapur)
     */
    public function generateKitchenTicketBytes(Order $order, ?PrinterSetting $settings = null): string
    {
        $settings = $settings ?? PrinterSetting::instance();
        $width = self::getMaxWidth($settings->paper_size);

        $out = '';
        $out .= self::CMD_INIT;

        // Big Header
        $out .= self::CMD_ALIGN_CENTER;
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_DOUBLE;
        $out .= '** TIKET DAPUR **' . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        // Big Table / Order Target Identifier
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_DOUBLE;
        $target = $order->table_number ? 'MEJA: ' . $order->table_number : 'ORDER: ' . strtoupper(str_replace('_', ' ', (string)$order->order_type));
        $out .= $target . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        $out .= self::CMD_ALIGN_LEFT;
        $createdAt = $order->created_at 
            ? ($order->created_at instanceof \DateTimeInterface 
                ? $order->created_at->format('H:i d/m/Y') 
                : date('H:i d/m/Y', strtotime((string)$order->created_at)))
            : date('H:i d/m/Y');
        $out .= $this->formatTwoColumns('No: #' . $order->order_number, $createdAt, $width) . self::LF;

        $out .= str_repeat('=', $width) . self::LF;

        // Hanya list menu beserta catatan khusus masing-masing pada menu
        foreach ($order->items as $item) {
            $out .= self::CMD_BOLD_ON;
            $out .= self::CMD_TEXT_BIG_H;
            $out .= $item->quantity . 'x ' . $item->product_name . self::LF;
            $out .= self::CMD_TEXT_NORMAL;
            $out .= self::CMD_BOLD_OFF;

            if (!empty($item->notes)) {
                $out .= self::CMD_BOLD_ON;
                $out .= '  * CATATAN: ' . strtoupper($item->notes) . self::LF;
                $out .= self::CMD_BOLD_OFF;
            }
            $out .= self::LF;
        }

        $out .= str_repeat('=', $width) . self::LF;

        if ($settings->cut_paper && strtolower($settings->paper_size) === '80mm') {
            $out .= self::CMD_CUT_PAPER;
        }

        return $out;
    }

    /**
     * Generate plain text representation formatted for 58mm/80mm monospace display
     */
    public function generateReceiptPlainText(Order $order, ?PrinterSetting $settings = null): string
    {
        $settings = $settings ?? PrinterSetting::instance();
        $width = self::getMaxWidth($settings->paper_size);

        $lines = [];
        $lines[] = $this->centerText($settings->store_name, $width);
        if (!empty($settings->store_tagline)) {
            $lines[] = $this->centerText($settings->store_tagline, $width);
        }
        $lines[] = $this->centerText($settings->store_address, $width);
        $lines[] = $this->centerText('Telp: ' . $settings->store_phone, $width);
        $lines[] = str_repeat('=', $width);

        $createdAt = $order->created_at 
            ? ($order->created_at instanceof \DateTimeInterface 
                ? $order->created_at->format('d/m/Y H:i') 
                : date('d/m/Y H:i', strtotime((string)$order->created_at)))
            : date('d/m/Y H:i');

        $lines[] = 'No: ' . $order->order_number;
        $orderTypeStr = 'Tipe: ' . strtoupper(str_replace('_', ' ', (string)$order->order_type));
        $lines[] = $this->formatTwoColumns($createdAt, $orderTypeStr, $width);
        $lines[] = 'Kasir: ' . ($order->cashier ? $order->cashier->name : 'Kasir POS');
        if (!empty($order->table_number)) {
            $lines[] = 'Meja / Lokasi: ' . $order->table_number;
        }
        $lines[] = 'Pelanggan: ' . ($order->customer_name ?: 'Pelanggan Umum');
        $lines[] = str_repeat('-', $width);

        $lines[] = $this->formatItemsHeader($width);
        $lines[] = str_repeat('-', $width);

        foreach ($order->items as $item) {
            $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Item');
            $nameLines = explode("\n", wordwrap($productName, $width, "\n", true));
            foreach ($nameLines as $nLine) {
                $lines[] = $nLine;
            }

            $qty = (int)($item->quantity ?? 1);
            $price = (float)($item->price ?? 0);
            $subtotal = (float)($item->subtotal ?? ($price * $qty));

            $qtyPrice = ' ' . $qty . ' x ' . number_format($price, 0, ',', '.');
            $subtotalStr = number_format($subtotal, 0, ',', '.');
            $lines[] = $this->formatTwoColumns($qtyPrice, $subtotalStr, $width);

            if (!empty($item->notes)) {
                $itemNotesLines = explode("\n", wordwrap($item->notes, max(10, $width - 5), "\n", true));
                foreach ($itemNotesLines as $idx => $nLine) {
                    $prefix = ($idx === 0) ? '   * ' : '     ';
                    $lines[] = $prefix . $nLine;
                }
            }
        }

        $lines[] = str_repeat('-', $width);
        $lines[] = $this->formatTwoColumns('Subtotal', 'Rp ' . number_format($order->subtotal, 0, ',', '.'), $width);
        if ($order->discount_amount > 0) {
            $lines[] = $this->formatTwoColumns('Diskon', '-Rp ' . number_format($order->discount_amount, 0, ',', '.'), $width);
        }
        if ($order->tax_amount > 0) {
            $lines[] = $this->formatTwoColumns('Pajak (PB1 ' . (int)$order->tax_rate . '%)', 'Rp ' . number_format($order->tax_amount, 0, ',', '.'), $width);
        }
        $lines[] = str_repeat('=', $width);
        $lines[] = $this->formatTwoColumns('TOTAL', 'Rp ' . number_format($order->total_amount, 0, ',', '.'), $width);

        $methodName = strtoupper(str_replace('_', ' ', (string)$order->payment_method));
        $lines[] = $this->formatTwoColumns('Metode Bayar', $methodName, $width);

        if ($order->payment_method === 'cash') {
            $lines[] = $this->formatTwoColumns('Tunai', 'Rp ' . number_format($order->cash_tendered, 0, ',', '.'), $width);
            $lines[] = $this->formatTwoColumns('Kembalian', 'Rp ' . number_format($order->change_due, 0, ',', '.'), $width);
        }

        $statusLabel = match(strtolower((string)$order->payment_status)) {
            'paid' => 'LUNAS (PAID)',
            'void' => 'BATAL (VOID)',
            'refunded' => 'REFUND',
            default => strtoupper((string)$order->payment_status),
        };
        $lines[] = $this->formatTwoColumns('Status', $statusLabel, $width);

        if (!empty($order->notes)) {
            $lines[] = str_repeat('-', $width);
            $lines[] = 'CATATAN PESANAN:';
            $orderNotesLines = explode("\n", wordwrap($order->notes, $width, "\n", true));
            foreach ($orderNotesLines as $onLine) {
                $lines[] = $onLine;
            }
        }

        $lines[] = str_repeat('=', $width);

        $footerRaw = $settings->receipt_footer ?: "Terima Kasih Atas Kunjungan Anda!\nBarang yang sudah dibeli tidak dapat ditukar.";
        $footerLines = explode("\n", str_replace("\r", '', $footerRaw));
        foreach ($footerLines as $fLine) {
            $trimmed = trim($fLine);
            if ($trimmed !== '') {
                $lines[] = $this->centerText($trimmed, $width);
            }
        }
        $lines[] = $this->centerText('Simpan struk ini sebagai bukti.', $width);
        $lines[] = $this->centerText('POS System Powered by Qurais Kitchen', $width);

        return implode("\n", $lines);
    }

    /**
     * Format two strings into left-aligned and right-aligned columns within given width
     */
    public function formatTwoColumns(string $left, string $right, int $width = 32): string
    {
        $leftLen = strlen($left);
        $rightLen = strlen($right);

        if ($leftLen + $rightLen >= $width) {
            $spaceAvailable = $width - $rightLen - 1;
            if ($spaceAvailable > 0) {
                $left = substr($left, 0, $spaceAvailable);
            }
            $leftLen = strlen($left);
        }

        $spaces = max(1, $width - $leftLen - $rightLen);
        return $left . str_repeat(' ', $spaces) . $right;
    }

    /**
     * Center text within width without truncating long text.
     * Long text is wrapped cleanly into multiple centered lines.
     */
    public function centerText(string $text, int $width = 32): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $lines = explode("\n", wordwrap($text, $width, "\n", true));
        $centeredLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $len = strlen($line);
            if ($len === 0) {
                continue;
            }

            if ($len >= $width) {
                $centeredLines[] = $line;
            } else {
                $leftPad = (int) floor(($width - $len) / 2);
                $rightPad = $width - $len - $leftPad;
                $centeredLines[] = str_repeat(' ', $leftPad) . $line . str_repeat(' ', $rightPad);
            }
        }

        return implode("\n", $centeredLines);
    }

    /**
     * Format header row for items
     */
    private function formatItemsHeader(int $width = 32): string
    {
        return $this->formatTwoColumns('Item / Qty x Harga', 'Subtotal', $width);
    }

    public function generateKitchenTicketPlainText(Order $order, ?PrinterSetting $settings = null): string
    {
        $settings = $settings ?? PrinterSetting::instance();
        $width = self::getMaxWidth($settings->paper_size);

        $lines = [];
        $lines[] = $this->centerText('** TIKET DAPUR **', $width);

        $target = $order->table_number ? 'MEJA: ' . $order->table_number : 'ORDER: ' . strtoupper(str_replace('_', ' ', (string)$order->order_type));
        $lines[] = $this->centerText($target, $width);

        $createdAt = $order->created_at 
            ? ($order->created_at instanceof \DateTimeInterface 
                ? $order->created_at->format('H:i d/m/Y') 
                : date('H:i d/m/Y', strtotime((string)$order->created_at)))
            : date('H:i d/m/Y');

        $lines[] = $this->formatTwoColumns('No: #' . $order->order_number, $createdAt, $width);
        $lines[] = str_repeat('=', $width);

        // Hanya list menu beserta catatan khusus masing-masing pada menu
        foreach ($order->items as $item) {
            $lines[] = "{$item->quantity}x {$item->product_name}";
            if (!empty($item->notes)) {
                $lines[] = '  * CATATAN: ' . strtoupper($item->notes);
            }
            $lines[] = '';
        }

        $lines[] = str_repeat('=', $width);

        return implode("\n", $lines);
    }

    /**
     * Generate binary ESC/POS byte stream for an individual Menu Item Ticket (KDS per-menu)
     */
    public function generateKitchenItemTicketBytes(OrderItem $item, ?PrinterSetting $settings = null): string
    {
        $order = $item->order;
        $settings = $settings ?? PrinterSetting::instance();
        $width = self::getMaxWidth($settings->paper_size);

        $out = '';
        $out .= self::CMD_INIT;

        // Big Header
        $out .= self::CMD_ALIGN_CENTER;
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_DOUBLE;
        $out .= '** TIKET MENU **' . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        $out .= str_repeat('=', $width) . self::LF;

        // Table / Target
        $out .= self::CMD_ALIGN_CENTER;
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_BIG_H;
        $target = $order?->table_number ? 'MEJA: ' . $order->table_number : 'ORDER: ' . strtoupper(str_replace('_', ' ', (string)($order?->order_type ?? 'dine_in')));
        $out .= $target . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        $out .= self::CMD_ALIGN_LEFT;
        $orderNumber = $order?->order_number ?? 'TRX';
        $timeStr = $order?->created_at ? date('H:i:s d/m/Y', strtotime((string)$order->created_at)) : date('H:i:s d/m/Y');
        $out .= $this->formatTwoColumns('No: ' . $orderNumber, $timeStr, $width) . self::LF;
        if (!empty($order?->customer_name)) {
            $out .= 'Nama: ' . $order->customer_name . self::LF;
        }
        $out .= str_repeat('=', $width) . self::LF;

        // Menu item in Big Bold Text
        $out .= self::CMD_ALIGN_CENTER;
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_DOUBLE;
        $out .= $item->quantity . 'x ' . $item->product_name . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        if (!empty($item->notes)) {
            $out .= self::CMD_ALIGN_LEFT;
            $out .= str_repeat('-', $width) . self::LF;
            $out .= self::CMD_BOLD_ON;
            $out .= 'CATATAN: ' . strtoupper($item->notes) . self::LF;
            $out .= self::CMD_BOLD_OFF;
        }

        $out .= str_repeat('=', $width) . self::LF;
        $out .= self::CMD_ALIGN_CENTER;
        $out .= self::CMD_BOLD_ON;
        $statusStr = $item->is_prepared ? 'STATUS: SIAP SAJI' : 'STATUS: PERSIAPAN MASAK';
        $out .= $statusStr . self::LF;
        $out .= self::CMD_BOLD_OFF;

        if ($settings->cut_paper && strtolower($settings->paper_size) === '80mm') {
            $out .= self::CMD_CUT_PAPER;
        }

        return $out;
    }

    /**
     * Generate plain text representation for an individual Menu Item Ticket
     */
    public function generateKitchenItemTicketPlainText(OrderItem $item, ?PrinterSetting $settings = null): string
    {
        $order = $item->order;
        $settings = $settings ?? PrinterSetting::instance();
        $width = self::getMaxWidth($settings->paper_size);

        $lines = [];
        $lines[] = $this->centerText('** TIKET MENU **', $width);
        $lines[] = str_repeat('=', $width);

        $target = $order?->table_number ? 'MEJA: ' . $order->table_number : 'ORDER: ' . strtoupper(str_replace('_', ' ', (string)($order?->order_type ?? 'dine_in')));
        $lines[] = $this->centerText($target, $width);
        $lines[] = str_repeat('=', $width);

        $createdAt = $order?->created_at 
            ? ($order->created_at instanceof \DateTimeInterface 
                ? $order->created_at->format('d/m/Y H:i') 
                : date('d/m/Y H:i', strtotime((string)$order->created_at)))
            : date('d/m/Y H:i');

        $lines[] = $this->formatTwoColumns('No: ' . ($order?->order_number ?? 'TRX'), $createdAt, $width);
        if (!empty($order?->customer_name)) {
            $lines[] = 'Pelanggan: ' . $order->customer_name;
        }
        $lines[] = str_repeat('=', $width);

        $lines[] = $this->centerText("{$item->quantity}x {$item->product_name}", $width);

        if (!empty($item->notes)) {
            $lines[] = str_repeat('-', $width);
            $lines[] = 'CATATAN: ' . $item->notes;
        }

        $lines[] = str_repeat('=', $width);
        $statusStr = $item->is_prepared ? 'STATUS: SIAP SAJI' : 'STATUS: PERSIAPAN MASAK';
        $lines[] = $this->centerText($statusStr, $width);

        return implode("\n", $lines);
    }
}
