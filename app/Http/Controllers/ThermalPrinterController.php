<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PrinterSetting;
use App\Services\EscPosReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ThermalPrinterController extends Controller
{
    protected EscPosReceiptService $escPosService;

    public function __construct(EscPosReceiptService $escPosService)
    {
        $this->escPosService = $escPosService;
    }

    /**
     * Get Receipt Data in Plain Text & Base64 ESC/POS formats
     */
    public function getReceipt(int $orderId): JsonResponse
    {
        $order = Order::with(['items.product', 'cashier'])->findOrFail($orderId);
        $settings = PrinterSetting::instance();

        $plainText = $this->escPosService->generateReceiptPlainText($order, $settings);
        $rawBytes = $this->escPosService->generateReceiptBytes($order, $settings);

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'paper_size' => $settings->paper_size,
            'plain_text' => $plainText,
            'escpos_base64' => base64_encode($rawBytes),
            'raw_byte_length' => strlen($rawBytes),
        ]);
    }

    /**
     * Get Kitchen Slip in Plain Text & Base64 ESC/POS formats
     */
    public function getKitchenTicket(int $orderId): JsonResponse
    {
        $order = Order::with(['items.product'])->findOrFail($orderId);
        $settings = PrinterSetting::instance();

        $rawBytes = $this->escPosService->generateKitchenTicketBytes($order, $settings);
        $plainText = $this->escPosService->generateKitchenTicketPlainText($order, $settings);

        return response()->json([
            'success' => true,
            'type' => 'kitchen',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'paper_size' => $settings->paper_size,
            'plain_text' => $plainText,
            'escpos_base64' => base64_encode($rawBytes),
            'kitchen_ticket_base64' => base64_encode($rawBytes),
            'raw_byte_length' => strlen($rawBytes),
        ]);
    }

    /**
     * Get individual item ticket in Plain Text & Base64 ESC/POS formats
     */
    public function getKitchenItemTicket(int $itemId): JsonResponse
    {
        $item = \App\Models\OrderItem::with('order')->findOrFail($itemId);
        $settings = PrinterSetting::instance();

        $rawBytes = $this->escPosService->generateKitchenItemTicketBytes($item, $settings);
        $plainText = $this->escPosService->generateKitchenItemTicketPlainText($item, $settings);

        return response()->json([
            'success' => true,
            'type' => 'kitchen_item',
            'item_id' => $item->id,
            'order_id' => $item->order_id,
            'order_number' => $item->order?->order_number ?? 'TRX',
            'product_name' => $item->product_name,
            'quantity' => $item->quantity,
            'paper_size' => $settings->paper_size,
            'plain_text' => $plainText,
            'escpos_base64' => base64_encode($rawBytes),
            'raw_byte_length' => strlen($rawBytes),
        ]);
    }

    /**
     * Download binary .bin ESC/POS file ready for raw printing
     */
    public function downloadEscPos(int $orderId): Response
    {
        $order = Order::with(['items', 'cashier'])->findOrFail($orderId);
        $settings = PrinterSetting::instance();
        $rawBytes = $this->escPosService->generateReceiptBytes($order, $settings);

        $filename = "receipt-{$order->order_number}.bin";

        return response($rawBytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($rawBytes),
        ]);
    }

    /**
     * Get list of detected Windows printers
     */
    public function getInstalledPrinters(): JsonResponse
    {
        $printers = $this->escPosService->getInstalledWindowsPrinters();
        return response()->json([
            'success' => true,
            'printers' => $printers,
        ]);
    }

    /**
     * Send receipt directly to physical Windows thermal printer
     */
    public function printOrderDirect(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with(['items', 'cashier'])->findOrFail($orderId);
        $settings = PrinterSetting::instance();
        $targetPrinter = $request->input('printer_name', $settings->windows_printer_name ?: 'Putian POS Thermal 2');
        $ticketType = $request->input('type', 'receipt'); // 'receipt' or 'kitchen'

        $rawBytes = $ticketType === 'kitchen' 
            ? $this->escPosService->generateKitchenTicketBytes($order, $settings)
            : $this->escPosService->generateReceiptBytes($order, $settings);

        $tPrintStart = microtime(true);
        $result = $this->escPosService->printDirectToWindowsPrinter($rawBytes, $targetPrinter);
        $tPrintEnd = microtime(true);
        $durationMs = (int) round(($tPrintEnd - $tPrintStart) * 1000);

        // Record print latency from order created until print completed
        $method = $ticketType === 'kitchen' ? 'kitchen_ticket' : 'manual_hardware';
        $printLog = $this->escPosService->recordPrintLog(
            $order,
            $method,
            $result,
            null,
            $durationMs,
            Auth::user()
        );

        \App\Services\FeatureLog::printer(
            'DIRECT_PRINT_ORDER',
            "Cetak tiket pesanan #{$order->order_number} ({$ticketType}) ke printer '{$targetPrinter}' | Hasil: " . ($result['success'] ? 'SUKSES' : 'GAGAL') . " | Durasi: {$durationMs} ms | Latency: {$printLog->latency_ms} ms",
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'printer' => $targetPrinter,
                'type' => $ticketType,
                'latency_ms' => $printLog->latency_ms,
                'duration_ms' => $durationMs,
                'success' => $result['success'],
            ]
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'printer' => $result['printer'],
            'order_number' => $order->order_number,
            'latency_ms' => $printLog->latency_ms,
            'duration_ms' => $durationMs,
            'printed_at' => $printLog->printed_at?->toIso8601String(),
        ]);
    }

    /**
     * Send individual menu item ticket directly to physical Windows thermal printer
     */
    public function printItemDirect(Request $request, int $itemId): JsonResponse
    {
        $item = \App\Models\OrderItem::with('order')->findOrFail($itemId);
        $settings = PrinterSetting::instance();
        $targetPrinter = $request->input('printer_name', $settings->windows_printer_name ?: 'Putian POS Thermal 2');

        $rawBytes = $this->escPosService->generateKitchenItemTicketBytes($item, $settings);

        $tPrintStart = microtime(true);
        $result = $this->escPosService->printDirectToWindowsPrinter($rawBytes, $targetPrinter);
        $tPrintEnd = microtime(true);
        $durationMs = (int) round(($tPrintEnd - $tPrintStart) * 1000);

        if ($item->order) {
            $this->escPosService->recordPrintLog(
                $item->order,
                'kitchen_item_ticket',
                $result,
                null,
                $durationMs,
                Auth::user()
            );
        }

        \App\Services\FeatureLog::printer(
            'DIRECT_PRINT_ITEM',
            "Cetak tiket menu '{$item->product_name}' (Order #{$item->order?->order_number}) ke printer '{$targetPrinter}' | Hasil: " . ($result['success'] ? 'SUKSES' : 'GAGAL') . " | Durasi: {$durationMs} ms",
            [
                'item_id' => $item->id,
                'item_name' => $item->product_name,
                'order_number' => $item->order?->order_number,
                'printer' => $targetPrinter,
                'duration_ms' => $durationMs,
                'success' => $result['success'],
            ]
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'printer' => $result['printer'],
            'item_id' => $item->id,
            'item_name' => $item->product_name,
            'order_number' => $item->order?->order_number,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Record client-side print latency (e.g. Web Serial USB print from browser)
     */
    public function recordClientPrintLatency(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with('cashier')->findOrFail($orderId);
        $method = $request->input('method', 'web_serial');
        $durationMs = $request->input('duration_ms', null);
        $success = $request->boolean('success', true);
        $printer = $request->input('printer', 'Web Serial USB Thermal');

        $result = [
            'success' => $success,
            'printer' => $printer,
            'message' => $request->input('message', 'Printed via Web Serial API'),
        ];

        $printLog = $this->escPosService->recordPrintLog(
            $order,
            $method,
            $result,
            null,
            $durationMs !== null ? (int)$durationMs : null,
            Auth::user()
        );

        \App\Services\FeatureLog::printer(
            'CLIENT_PRINT_LATENCY',
            "Latensi cetak browser client dicatat untuk Order #{$order->order_number} (Metode: {$method}, Printer: {$printer}, Latency: {$printLog->latency_ms} ms)",
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'latency_ms' => $printLog->latency_ms,
                'method' => $method,
                'printer' => $printer,
            ]
        );

        return response()->json([
            'success' => true,
            'order_number' => $order->order_number,
            'latency_ms' => $printLog->latency_ms,
            'printed_at' => $printLog->printed_at?->toIso8601String(),
        ]);
    }

    /**
     * Get current printer settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = PrinterSetting::instance();
        return response()->json([
            'success' => true,
            'settings' => $settings,
            'installed_printers' => $this->escPosService->getInstalledWindowsPrinters(),
        ]);
    }

    /**
     * Update printer settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'store_name' => 'required|string|max:100',
            'store_tagline' => 'nullable|string|max:150',
            'store_address' => 'required|string|max:200',
            'store_phone' => 'required|string|max:50',
            'paper_size' => 'required|string|in:58mm,80mm',
            'windows_printer_name' => 'nullable|string|max:150',
            'direct_hardware_print' => 'boolean',
            'receipt_footer' => 'nullable|string|max:500',
            'auto_print_cashier' => 'boolean',
            'auto_print_kitchen' => 'boolean',
            'cut_paper' => 'boolean',
            'open_cash_drawer' => 'boolean',
        ]);

        $settings = PrinterSetting::instance();
        $settings->update($request->only([
            'store_name',
            'store_tagline',
            'store_address',
            'store_phone',
            'paper_size',
            'windows_printer_name',
            'direct_hardware_print',
            'receipt_footer',
            'auto_print_cashier',
            'auto_print_kitchen',
            'cut_paper',
            'open_cash_drawer',
        ]));

        \App\Services\FeatureLog::printer(
            'SETTINGS_UPDATED',
            "Pengaturan printer thermal diperbarui: Kertas={$settings->paper_size}, Printer default='{$settings->windows_printer_name}', DirectPrint=" . ($settings->direct_hardware_print ? 'ON' : 'OFF'),
            [
                'paper_size' => $settings->paper_size,
                'windows_printer_name' => $settings->windows_printer_name,
                'direct_hardware_print' => $settings->direct_hardware_print,
                'auto_print_cashier' => $settings->auto_print_cashier,
                'auto_print_kitchen' => $settings->auto_print_kitchen,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan printer thermal berhasil diperbarui!',
            'settings' => $settings,
        ]);
    }

    /**
     * Generate test print pattern and optionally send directly to hardware printer
     */
    public function testPrint(Request $request): JsonResponse
    {
        $settings = PrinterSetting::instance();
        $width = EscPosReceiptService::getMaxWidth($settings->paper_size);

        $out = EscPosReceiptService::CMD_INIT;
        $out .= EscPosReceiptService::CMD_ALIGN_CENTER;
        $out .= EscPosReceiptService::CMD_BOLD_ON;
        $out .= EscPosReceiptService::CMD_TEXT_DOUBLE;
        $out .= 'TEST PRINTER POS' . EscPosReceiptService::LF;
        $out .= EscPosReceiptService::CMD_TEXT_NORMAL;
        $out .= EscPosReceiptService::CMD_BOLD_OFF;
        $out .= 'Format: ' . $settings->paper_size . ' (' . $width . ' Kolom)' . EscPosReceiptService::LF;
        $out .= 'Printer: ' . ($settings->windows_printer_name ?: 'Putian POS Thermal 2') . EscPosReceiptService::LF;
        $out .= date('d/m/Y H:i:s') . EscPosReceiptService::LF;
        $out .= str_repeat('=', $width) . EscPosReceiptService::LF;
        $out .= EscPosReceiptService::CMD_ALIGN_LEFT;
        $out .= 'Perataan Kiri    [OK]' . EscPosReceiptService::LF;
        $out .= EscPosReceiptService::CMD_ALIGN_CENTER;
        $out .= 'Perataan Tengah  [OK]' . EscPosReceiptService::LF;
        $out .= EscPosReceiptService::CMD_ALIGN_RIGHT;
        $out .= 'Perataan Kanan   [OK]' . EscPosReceiptService::LF;
        $out .= EscPosReceiptService::CMD_ALIGN_CENTER;
        $out .= str_repeat('-', $width) . EscPosReceiptService::LF;
        $out .= 'Karakter: 0123456789 ABCDEF' . EscPosReceiptService::LF;
        $out .= 'Thermal Head: Normal & Siap' . EscPosReceiptService::LF;
        $out .= str_repeat('=', $width) . EscPosReceiptService::LF;

        if ($settings->cut_paper && strtolower($settings->paper_size) === '80mm') {
            $out .= EscPosReceiptService::CMD_CUT_PAPER;
        }

        $directResult = null;
        if ($settings->direct_hardware_print || $request->boolean('force_direct')) {
            $directResult = $this->escPosService->printDirectToWindowsPrinter($out, $settings->windows_printer_name);
        }

        \App\Services\FeatureLog::printer(
            'TEST_PRINT',
            "Test print ESC/POS dijalankan ke printer '{$settings->windows_printer_name}' (Ukuran: {$settings->paper_size}, Direct: " . ($directResult ? 'YA' : 'TIDAK') . ")",
            [
                'paper_size' => $settings->paper_size,
                'printer' => $settings->windows_printer_name,
                'direct_result' => $directResult,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $directResult ? $directResult['message'] : 'Test print payload generated',
            'escpos_base64' => base64_encode($out),
            'paper_size' => $settings->paper_size,
            'direct_result' => $directResult,
        ]);
    }
}
