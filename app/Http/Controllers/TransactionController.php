<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Models\Product;
use App\Services\EscPosReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    protected EscPosReceiptService $escPosService;

    public function __construct(EscPosReceiptService $escPosService)
    {
        $this->escPosService = $escPosService;
    }

    /**
     * Show Transactions Dashboard view
     */
    public function index(): View
    {
        return view('transactions.index', [
            'printerSettings' => PrinterSetting::instance(),
        ]);
    }

    /**
     * Get paginated transactions with flexible filtering
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'cashier'])->orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('table_number', 'LIKE', "%{$search}%");
            });
        }

        // Payment status filter
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        // Kitchen status filter
        if ($request->filled('kitchen_status')) {
            $query->where('kitchen_status', $request->input('kitchen_status'));
        }

        // Order type filter
        if ($request->filled('order_type')) {
            $query->where('order_type', $request->input('order_type'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Summary metrics before pagination
        $summaryQuery = clone $query;
        $totalRevenue = (float)$summaryQuery->where('payment_status', 'paid')->sum('total_amount');
        $totalOrdersCount = (int)$summaryQuery->count();
        $paidCount = (int)(clone $query)->where('payment_status', 'paid')->count();

        $perPage = (int)$request->input('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrdersCount,
                'paid_orders' => $paidCount,
            ],
        ]);
    }

    /**
     * Get order detail
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items.product', 'cashier'])->findOrFail($id);
        $settings = PrinterSetting::instance();

        $plainText = $this->escPosService->generateReceiptPlainText($order, $settings);
        $receiptBytes = $this->escPosService->generateReceiptBytes($order, $settings);

        \App\Services\FeatureLog::transaction('SHOW_ORDER', "Detail transaksi #{$order->order_number} dibuka", [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ], 'debug');

        return response()->json([
            'success' => true,
            'order' => $order,
            'receipt' => [
                'plain_text' => $plainText,
                'escpos_base64' => base64_encode($receiptBytes),
            ],
        ]);
    }

    /**
     * Void or cancel transaction and restore product stock
     */
    public function voidOrder(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($id);

            if ($order->payment_status === 'void' || $order->payment_status === 'refunded') {
                \App\Services\FeatureLog::transaction('VOID_REJECTED', "Percobaan VOID ditolak: Transaksi #{$order->order_number} sudah berstatus void/refund sebelumnya", [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_status' => $order->payment_status,
                ], 'warning');

                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi ini sudah berstatus void/refund sebelumnya.',
                ], 422);
            }

            // Restore product stock
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }

            $reason = $request->input('reason', 'Dibatalkan oleh kasir');
            $previousStatus = $order->kitchen_status;
            $order->update([
                'payment_status' => 'void',
                'kitchen_status' => 'cancelled',
                'notes' => ($order->notes ? $order->notes . ' | ' : '') . 'VOID: ' . $reason,
            ]);

            \App\Services\FeatureLog::transaction('ORDER_VOIDED', "Transaksi #{$order->order_number} di-VOID. Alasan: '{$reason}'. Stok dipulihkan untuk {$order->items->count()} item", [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'items_restored' => $order->items->count(),
                'reason' => $reason,
            ]);

            // Notify KDS
            Cache::put('kds_last_update', microtime(true), 3600);
            event(new OrderStatusUpdated($order, $previousStatus));

            return response()->json([
                'success' => true,
                'message' => "Transaksi #{$order->order_number} berhasil di-VOID dan stok dikembalikan.",
                'order' => $order,
            ]);
        });
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Order::with(['cashier'])->orderBy('created_at', 'desc');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $orders = $query->get();
        $filename = 'laporan-transaksi-' . date('Y-m-d-His') . '.csv';

        \App\Services\FeatureLog::transaction('CSV_EXPORTED', "Laporan transaksi diekspor ({$orders->count()} data transaksi)", [
            'total_rows' => $orders->count(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'filename' => $filename,
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'No Order',
                'Waktu',
                'Pelanggan',
                'Meja/Lokasi',
                'Tipe Order',
                'Metode Bayar',
                'Status Bayar',
                'Subtotal',
                'Diskon',
                'Pajak',
                'Total',
                'Status Dapur',
                'Kasir',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->customer_name,
                    $order->table_number,
                    $order->order_type,
                    $order->payment_method,
                    $order->payment_status,
                    $order->subtotal,
                    $order->discount_amount,
                    $order->tax_amount,
                    $order->total_amount,
                    $order->kitchen_status,
                    $order->cashier ? $order->cashier->name : '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Update special notes for a specific menu item in an order
     */
    public function updateItemNote(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $item = OrderItem::with('order')->findOrFail($itemId);
        $item->update([
            'notes' => $validated['notes'] ?? '',
        ]);

        \App\Services\FeatureLog::transaction('ITEM_NOTE_UPDATED', "Catatan menu '{$item->product_name}' (Order #{$item->order?->order_number}) diperbarui menjadi: '{$item->notes}'", [
            'item_id' => $item->id,
            'order_id' => $item->order_id,
            'notes' => $item->notes,
        ]);

        // Notify KDS in real-time
        if ($item->order) {
            Cache::put('kds_last_update', microtime(true), 3600);
            event(new OrderStatusUpdated($item->order, $item->order->kitchen_status));
        }

        return response()->json([
            'success' => true,
            'message' => 'Catatan menu berhasil diperbarui.',
            'item' => $item,
        ]);
    }

    /**
     * Update order-level notes
     */
    public function updateOrderNote(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $order = Order::with('items')->findOrFail($id);
        $order->update([
            'notes' => $validated['notes'] ?? '',
        ]);

        \App\Services\FeatureLog::transaction('ORDER_NOTE_UPDATED', "Catatan transaksi #{$order->order_number} diperbarui menjadi: '{$order->notes}'", [
            'order_id' => $order->id,
            'notes' => $order->notes,
        ]);

        // Notify KDS in real-time
        Cache::put('kds_last_update', microtime(true), 3600);
        event(new OrderStatusUpdated($order, $order->kitchen_status));

        return response()->json([
            'success' => true,
            'message' => 'Catatan transaksi berhasil diperbarui.',
            'order' => $order,
        ]);
    }
}
