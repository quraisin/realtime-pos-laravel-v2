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
     * Get list of installed printers across all OS (Windows, macOS, Linux)
     */
    public function getInstalledWindowsPrinters(): array
    {
        $os = strtoupper(substr(PHP_OS, 0, 3));

        // 1. Windows (PowerShell Spooler)
        if ($os === 'WIN') {
            try {
                $cmd = 'powershell -NoProfile -Command "Get-Printer | Select-Object -ExpandProperty Name"';
                $output = shell_exec($cmd);
                if ($output) {
                    $lines = array_map('trim', explode("\n", $output));
                    $printers = array_values(array_filter($lines, fn($line) => !empty($line)));
                    if (!empty($printers)) {
                        return $printers;
                    }
                }
            } catch (\Throwable $e) {
                // fallback
            }

            return ['Putian POS Thermal 2'];
        }

        // 2. macOS (Darwin) & Linux (CUPS + Direct USB/Serial devices)
        $printers = [];

        // 2a. Check CUPS queues via lpstat -e and lpstat -a
        try {
            $lpstatOut = shell_exec('/usr/bin/lpstat -e 2>/dev/null || lpstat -e 2>/dev/null');
            if ($lpstatOut) {
                foreach (explode("\n", trim($lpstatOut)) as $line) {
                    $name = trim($line);
                    if ($name !== '' && !in_array($name, $printers, true)) {
                        $printers[] = $name;
                    }
                }
            }

            if (empty($printers)) {
                $lpstatA = shell_exec('/usr/bin/lpstat -a 2>/dev/null || lpstat -a 2>/dev/null');
                if ($lpstatA) {
                    foreach (explode("\n", trim($lpstatA)) as $line) {
                        if (preg_match('/^([^\s]+)\s+accepting/i', trim($line), $m)) {
                            if (!in_array($m[1], $printers, true)) {
                                $printers[] = $m[1];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2b. Check connected USB printers via lpinfo -v (auto-register raw queue if needed)
        try {
            $lpinfoOut = shell_exec('/usr/sbin/lpinfo -v 2>/dev/null || lpinfo -v 2>/dev/null');
            if ($lpinfoOut) {
                foreach (explode("\n", trim($lpinfoOut)) as $line) {
                    if (preg_match('/(usb:\/\/[^\s]+)/i', trim($line), $m)) {
                        $usbUri = $m[1];
                        $queueName = $this->resolveOrRegisterUsbCupsQueue($usbUri, $printers);
                        if ($queueName && !in_array($queueName, $printers, true)) {
                            $printers[] = $queueName;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2c. Check direct USB/Serial device nodes on macOS & Linux
        $devicePatterns = [
            '/dev/cu.usb*',
            '/dev/tty.usb*',
            '/dev/usb/lp*',
            '/dev/ttyUSB*',
            '/dev/ttyACM*',
        ];
        foreach ($devicePatterns as $pattern) {
            $matches = glob($pattern) ?: [];
            foreach ($matches as $dev) {
                if (!in_array($dev, $printers, true)) {
                    $printers[] = $dev;
                }
            }
        }

        if (empty($printers)) {
            $printers[] = 'Putian_POS_Thermal';
        }

        return $printers;
    }

    /**
     * Resolve an existing CUPS queue for a usb:// URI or auto-register a Raw CUPS queue
     */
    protected function resolveOrRegisterUsbCupsQueue(string $usbUri, array $existingQueues = []): ?string
    {
        try {
            $lpstatV = shell_exec('/usr/bin/lpstat -v 2>/dev/null || lpstat -v 2>/dev/null');
            if ($lpstatV) {
                foreach (explode("\n", trim($lpstatV)) as $line) {
                    // Format: device for <QueueName>: <URI>
                    if (preg_match('/device for ([^\s:]+):\s*(.+)$/i', trim($line), $m)) {
                        if (trim($m[2]) === $usbUri) {
                            return trim($m[1]);
                        }
                    }
                }
            }

            // Build a clean CUPS queue name from the USB URI
            $parsed = parse_url($usbUri);
            $make = preg_replace('/[^A-Za-z0-9_]/', '_', $parsed['host'] ?? 'USB');
            $model = preg_replace('/[^A-Za-z0-9_]/', '_', trim($parsed['path'] ?? 'Thermal_Printer', '/'));
            $queueName = trim("{$make}_{$model}", '_') ?: 'Putian_POS_Thermal';

            // Auto-register raw queue in CUPS
            $cmd = sprintf(
                '(/usr/sbin/lpadmin -p %s -E -v %s -m raw 2>/dev/null || lpadmin -p %s -E -v %s -m raw 2>/dev/null)',
                escapeshellarg($queueName),
                escapeshellarg($usbUri),
                escapeshellarg($queueName),
                escapeshellarg($usbUri)
            );
            shell_exec($cmd);

            return $queueName;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Send raw ESC/POS bytes directly to Thermal Printer across Windows, macOS, and Linux
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

        $os = strtoupper(substr(PHP_OS, 0, 3));

        // 1. Windows OS: Use PowerShell Raw Print Spooler script
        if ($os === 'WIN') {
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

        // 2. macOS (Darwin) & Linux: Support Direct Device Node (/dev/...) or CUPS Raw Spooler (lp / lpr)
        // 2a. If targetPrinter is a direct /dev/ node
        if (str_starts_with($targetPrinter, '/dev/')) {
            try {
                if (file_exists($targetPrinter) && is_writable($targetPrinter)) {
                    $written = @file_put_contents($targetPrinter, $rawBytes);
                    @unlink($tempFile);
                    if ($written !== false) {
                        return [
                            'success' => true,
                            'printer' => $targetPrinter,
                            'output' => "Wrote {$written} bytes to {$targetPrinter}",
                            'message' => "Struk berhasil dikirim ke port USB: {$targetPrinter}",
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // fallback to CUPS below
            }
        }

        // 2b. Resolve CUPS queue name on macOS / Linux
        $installedPrinters = $this->getInstalledWindowsPrinters();
        $cupsTargets = array_values(array_filter($installedPrinters, fn($p) => !str_starts_with($p, '/dev/')));

        $resolvedQueue = $targetPrinter;
        $sanitizedTarget = preg_replace('/[^A-Za-z0-9_-]/', '_', $targetPrinter);

        if (!in_array($resolvedQueue, $cupsTargets, true)) {
            if (in_array($sanitizedTarget, $cupsTargets, true)) {
                $resolvedQueue = $sanitizedTarget;
            } elseif (!empty($cupsTargets)) {
                // Prefer any queue matching POS / Thermal / Putian / USB, otherwise first available CUPS printer
                $matched = null;
                foreach ($cupsTargets as $candidate) {
                    if (preg_match('/(pos|thermal|putian|xprinter|eppos|receipt|usb|raw)/i', $candidate)) {
                        $matched = $candidate;
                        break;
                    }
                }
                $resolvedQueue = $matched ?: $cupsTargets[0];
            } else {
                $resolvedQueue = $sanitizedTarget;
            }
        }

        // Try printing via CUPS `lp -d <queue> -o raw`
        $cmd = sprintf(
            '(/usr/bin/lp -d %s -o raw %s 2>&1 || lp -d %s -o raw %s 2>&1)',
            escapeshellarg($resolvedQueue),
            escapeshellarg($tempFile),
            escapeshellarg($resolvedQueue),
            escapeshellarg($tempFile)
        );

        $output = (string) shell_exec($cmd);
        $isSuccess = str_contains($output, 'request id is') || (trim($output) === '' && !str_contains(strtolower($output), 'error'));

        // Fallback: try `lpr -P <queue> -o raw` if `lp` failed
        if (!$isSuccess || str_contains(strtolower($output), 'lp:') || str_contains(strtolower($output), 'error')) {
            $lprCmd = sprintf(
                '(/usr/bin/lpr -P %s -o raw %s 2>&1 || lpr -P %s -o raw %s 2>&1)',
                escapeshellarg($resolvedQueue),
                escapeshellarg($tempFile),
                escapeshellarg($resolvedQueue),
                escapeshellarg($tempFile)
            );
            $lprOut = (string) shell_exec($lprCmd);
            if (trim($lprOut) === '') {
                $isSuccess = true;
                $output = "Printed via lpr raw queue ({$resolvedQueue})";
            } else {
                $isSuccess = false;
                $output = trim($output . ' | ' . $lprOut);
            }
        }

        @unlink($tempFile);

        return [
            'success' => $isSuccess,
            'printer' => $resolvedQueue,
            'output' => trim($output),
            'message' => $isSuccess
                ? "Struk berhasil dicetak ke printer USB ({$resolvedQueue})"
                : "Printer belum terdeteksi di CUPS ({$resolvedQueue}). Gunakan tombol 'Cetak USB (Semua OS)' atau daftarkan printer di CUPS: {$output}",
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

        // Initialize printer & set left alignment as deterministic baseline
        // (All centering is calculated in software via centerText() with wordwrap so words never split mid-word)
        $out .= self::CMD_INIT;
        $out .= self::CMD_ALIGN_LEFT;

        // Store Header (Centered with word-wrapping, Double Height Bold title)
        $storeName = strtoupper(trim((string)($settings->store_name ?: 'QURAIS KITCHEN')));
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_BIG_H;
        $out .= $this->centerText($storeName, $width) . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        if (!empty($settings->store_tagline)) {
            $out .= $this->centerText($settings->store_tagline, $width) . self::LF;
        }
        if (!empty($settings->store_address)) {
            $out .= $this->centerText($settings->store_address, $width) . self::LF;
        }
        if (!empty($settings->store_phone)) {
            $out .= $this->centerText('Telp: ' . $settings->store_phone, $width) . self::LF;
        }
        $out .= str_repeat('=', $width) . self::LF;

        // Order Meta (Clean Two-Column & Wrapped Left-Aligned)
        $createdAt = $order->created_at
            ? ($order->created_at instanceof \DateTimeInterface
                ? $order->created_at->format('d/m/Y H:i')
                : date('d/m/Y H:i', strtotime((string)$order->created_at)))
            : date('d/m/Y H:i');

        $orderTypeStr = strtoupper(str_replace('_', ' ', (string)$order->order_type));
        $out .= $this->formatTwoColumns('No: ' . $order->order_number, $orderTypeStr, $width) . self::LF;
        $out .= $this->formatTwoColumns('Tgl: ' . $createdAt, $order->table_number ? ('Meja: ' . $order->table_number) : '', $width) . self::LF;
        $out .= $this->wrapLine('Kasir: ' . ($order->cashier ? $order->cashier->name : 'Kasir POS'), $width) . self::LF;
        $out .= $this->wrapLine('Pelanggan: ' . ($order->customer_name ?: 'Pelanggan Umum'), $width) . self::LF;
        $out .= str_repeat('-', $width) . self::LF;

        // Items Header
        $out .= self::CMD_BOLD_ON;
        $out .= $this->formatItemsHeader($width) . self::LF;
        $out .= self::CMD_BOLD_OFF;
        $out .= str_repeat('-', $width) . self::LF;

        // Items List
        foreach ($order->items as $item) {
            $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Item');
            $out .= $this->wrapLine($productName, $width) . self::LF;

            $qty = (int)($item->quantity ?? 1);
            $price = (float)($item->price ?? 0);
            $subtotal = (float)($item->subtotal ?? ($price * $qty));
            $qtyPrice = '  ' . $qty . ' x ' . number_format($price, 0, ',', '.');
            $subtotalStr = number_format($subtotal, 0, ',', '.');
            $out .= $this->formatTwoColumns($qtyPrice, $subtotalStr, $width) . self::LF;

            if (!empty($item->notes)) {
                $noteLines = explode("\n", wordwrap(trim((string)$item->notes), max(10, $width - 5), "\n", true));
                foreach ($noteLines as $idx => $nLine) {
                    $prefix = ($idx === 0) ? '   * ' : '     ';
                    $out .= $prefix . $nLine . self::LF;
                }
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
        $out .= self::CMD_TEXT_BIG_H;
        $out .= $this->formatTwoColumns('TOTAL', 'Rp ' . number_format($order->total_amount, 0, ',', '.'), $width) . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;
        $out .= str_repeat('-', $width) . self::LF;

        $out .= $this->formatTwoColumns('Metode Bayar', strtoupper(str_replace('_', ' ', (string)$order->payment_method)), $width) . self::LF;
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
            $out .= 'CATATAN PESANAN:' . self::LF;
            $out .= $this->wrapLine((string)$order->notes, $width) . self::LF;
        }

        $out .= str_repeat('=', $width) . self::LF;

        // Footer Message (Word-wrapped & centered cleanly per line so no word is ever split in half)
        $footerRaw = $settings->receipt_footer ?: "Terima Kasih Atas Kunjungan Anda!\nBarang yang dibeli tidak dapat ditukar.";
        $footerLines = explode("\n", str_replace("\r", '', $footerRaw));
        foreach ($footerLines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $out .= $this->centerText($trimmed, $width) . self::LF;
            }
        }
        $out .= $this->centerText('Simpan struk ini sebagai bukti.', $width) . self::LF;
        $out .= $this->centerText('Powered by Qurais Kitchen POS', $width) . self::LF;

        // Feed 4 blank lines so the bottom of the receipt clears the serrated tear bar cleanly
        $out .= str_repeat(self::LF, 4);

        // Cash drawer kick placed at the end so solenoid current spike does not disrupt header printing
        if ($settings->open_cash_drawer && $order->payment_method === 'cash') {
            $out .= self::CMD_DRAWER_KICK;
        }

        // Cut Paper: Only send to 80mm printers with auto-cutter mechanism.
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
        $out .= self::CMD_ALIGN_LEFT;

        // Big Header
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_BIG_H;
        $out .= $this->centerText('** TIKET DAPUR **', $width) . self::LF;

        // Big Table / Order Target Identifier
        $target = $order->table_number ? 'MEJA: ' . $order->table_number : 'ORDER: ' . strtoupper(str_replace('_', ' ', (string)$order->order_type));
        $out .= $this->centerText($target, $width) . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

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
            $out .= $this->wrapLine($item->quantity . 'x ' . $item->product_name, $width) . self::LF;
            $out .= self::CMD_TEXT_NORMAL;
            $out .= self::CMD_BOLD_OFF;

            if (!empty($item->notes)) {
                $out .= self::CMD_BOLD_ON;
                $out .= $this->wrapLine('  * CATATAN: ' . strtoupper($item->notes), $width) . self::LF;
                $out .= self::CMD_BOLD_OFF;
            }
            $out .= self::LF;
        }

        $out .= str_repeat('=', $width) . self::LF;
        $out .= str_repeat(self::LF, 4);

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
        $lines[] = $this->centerText(strtoupper((string)($settings->store_name ?: 'QURAIS KITCHEN')), $width);
        if (!empty($settings->store_tagline)) {
            $lines[] = $this->centerText($settings->store_tagline, $width);
        }
        if (!empty($settings->store_address)) {
            $lines[] = $this->centerText($settings->store_address, $width);
        }
        if (!empty($settings->store_phone)) {
            $lines[] = $this->centerText('Telp: ' . $settings->store_phone, $width);
        }
        $lines[] = str_repeat('=', $width);

        $createdAt = $order->created_at 
            ? ($order->created_at instanceof \DateTimeInterface 
                ? $order->created_at->format('d/m/Y H:i') 
                : date('d/m/Y H:i', strtotime((string)$order->created_at)))
            : date('d/m/Y H:i');

        $orderTypeStr = strtoupper(str_replace('_', ' ', (string)$order->order_type));
        $lines[] = $this->formatTwoColumns('No: ' . $order->order_number, $orderTypeStr, $width);
        $lines[] = $this->formatTwoColumns('Tgl: ' . $createdAt, $order->table_number ? ('Meja: ' . $order->table_number) : '', $width);
        $lines[] = $this->wrapLine('Kasir: ' . ($order->cashier ? $order->cashier->name : 'Kasir POS'), $width);
        $lines[] = $this->wrapLine('Pelanggan: ' . ($order->customer_name ?: 'Pelanggan Umum'), $width);
        $lines[] = str_repeat('-', $width);

        $lines[] = $this->formatItemsHeader($width);
        $lines[] = str_repeat('-', $width);

        foreach ($order->items as $item) {
            $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Item');
            $lines[] = $this->wrapLine($productName, $width);

            $qty = (int)($item->quantity ?? 1);
            $price = (float)($item->price ?? 0);
            $subtotal = (float)($item->subtotal ?? ($price * $qty));

            $qtyPrice = '  ' . $qty . ' x ' . number_format($price, 0, ',', '.');
            $subtotalStr = number_format($subtotal, 0, ',', '.');
            $lines[] = $this->formatTwoColumns($qtyPrice, $subtotalStr, $width);

            if (!empty($item->notes)) {
                $itemNotesLines = explode("\n", wordwrap(trim((string)$item->notes), max(10, $width - 5), "\n", true));
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
        $lines[] = str_repeat('-', $width);

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
            $lines[] = $this->wrapLine((string)$order->notes, $width);
        }

        $lines[] = str_repeat('=', $width);

        $footerRaw = $settings->receipt_footer ?: "Terima Kasih Atas Kunjungan Anda!\nBarang yang dibeli tidak dapat ditukar.";
        $footerLines = explode("\n", str_replace("\r", '', $footerRaw));
        foreach ($footerLines as $fLine) {
            $trimmed = trim($fLine);
            if ($trimmed !== '') {
                $lines[] = $this->centerText($trimmed, $width);
            }
        }
        $lines[] = $this->centerText('Simpan struk ini sebagai bukti.', $width);
        $lines[] = $this->centerText('Powered by Qurais Kitchen POS', $width);

        return implode("\n", $lines);
    }

    /**
     * Wrap long text cleanly at word boundaries to fit column width
     */
    public function wrapLine(string $text, int $width = 32): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        return wordwrap($text, $width, "\n", true);
    }

    /**
     * Format two strings into left-aligned and right-aligned columns within given width
     */
    public function formatTwoColumns(string $left, string $right, int $width = 32): string
    {
        $left = trim($left, "\r\n");
        $right = trim($right, "\r\n");

        if ($right === '') {
            return $this->wrapLine($left, $width);
        }

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
                $centeredLines[] = str_repeat(' ', $leftPad) . $line;
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
            $lines[] = $this->wrapLine("{$item->quantity}x {$item->product_name}", $width);
            if (!empty($item->notes)) {
                $lines[] = $this->wrapLine('  * CATATAN: ' . strtoupper($item->notes), $width);
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
        $out .= self::CMD_ALIGN_LEFT;

        // Big Header
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_BIG_H;
        $out .= $this->centerText('** TIKET MENU **', $width) . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        $out .= str_repeat('=', $width) . self::LF;

        // Table / Target
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_BIG_H;
        $target = $order?->table_number ? 'MEJA: ' . $order->table_number : 'ORDER: ' . strtoupper(str_replace('_', ' ', (string)($order?->order_type ?? 'dine_in')));
        $out .= $this->centerText($target, $width) . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        $orderNumber = $order?->order_number ?? 'TRX';
        $timeStr = $order?->created_at ? date('H:i d/m/Y', strtotime((string)$order->created_at)) : date('H:i d/m/Y');
        $out .= $this->formatTwoColumns('No: ' . $orderNumber, $timeStr, $width) . self::LF;
        if (!empty($order?->customer_name)) {
            $out .= $this->wrapLine('Nama: ' . $order->customer_name, $width) . self::LF;
        }
        $out .= str_repeat('=', $width) . self::LF;

        // Menu item in Big Bold Text
        $out .= self::CMD_BOLD_ON;
        $out .= self::CMD_TEXT_BIG_H;
        $out .= $this->centerText($item->quantity . 'x ' . $item->product_name, $width) . self::LF;
        $out .= self::CMD_TEXT_NORMAL;
        $out .= self::CMD_BOLD_OFF;

        if (!empty($item->notes)) {
            $out .= str_repeat('-', $width) . self::LF;
            $out .= self::CMD_BOLD_ON;
            $out .= $this->wrapLine('CATATAN: ' . strtoupper($item->notes), $width) . self::LF;
            $out .= self::CMD_BOLD_OFF;
        }

        $out .= str_repeat('=', $width) . self::LF;
        $out .= self::CMD_BOLD_ON;
        $statusStr = $item->is_prepared ? 'STATUS: SIAP SAJI' : 'STATUS: PERSIAPAN MASAK';
        $out .= $this->centerText($statusStr, $width) . self::LF;
        $out .= self::CMD_BOLD_OFF;

        $out .= str_repeat(self::LF, 4);

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
