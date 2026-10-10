<?php

namespace App\Http\Controllers;

use App\Events\NewOrderCreated;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Models\Product;
use App\Services\EscPosReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PosController extends Controller
{
    protected EscPosReceiptService $escPosService;

    public function __construct(EscPosReceiptService $escPosService)
    {
        $this->escPosService = $escPosService;
    }

    /**
     * Show Cashier POS interface
     */
    public function index(): View
    {
        $printerSettings = PrinterSetting::instance();
        return view('pos.index', [
            'printerSettings' => $printerSettings,
        ]);
    }

    /**
     * Get list of product categories
     */
    public function getCategories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->withCount(['products' => function ($query) {
                $query->where('is_available', true);
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Get products with optional category and search filters
     */
    public function getProducts(Request $request): JsonResponse
    {
        $query = Product::with('category')->where('is_available', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'products' => $products,
        ]);
    }

    /**
     * Create new order and trigger realtime kitchen notification + receipt generation
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'customer_name' => 'nullable|string|max:100',
            'table_number' => 'nullable|string|max:50',
            'order_type' => 'required|string|in:dine_in,take_away,delivery',
            'payment_method' => 'required|string|in:cash,qris,debit_card,credit_card',
            'cash_tendered' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:200',
        ]);

        return DB::transaction(function () use ($request) {
            $itemsData = $request->input('items');
            $subtotal = 0;
            $orderItemsToCreate = [];
            $productsToDecrement = [];

            // Calculate item subtotals and check stock sufficiency
            foreach ($itemsData as $itemInput) {
                $product = Product::lockForUpdate()->findOrFail($itemInput['product_id']);

                $qty = (int)$itemInput['quantity'];

                // Validate stock availability
                if ($product->stock < $qty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok untuk produk '{$product->name}' tidak mencukupi (Tersisa: {$product->stock} porsi)!",
                    ], 422);
                }

                $itemSubtotal = $product->price * $qty;
                $subtotal += $itemSubtotal;

                $productsToDecrement[] = [
                    'product' => $product,
                    'qty' => $qty,
                ];

                $orderItemsToCreate[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $qty,
                    'subtotal' => $itemSubtotal,
                    'notes' => $itemInput['notes'] ?? null,
                    'is_prepared' => false,
                ];
            }

            $discountAmount = (float)($request->input('discount_amount', 0));
            $taxRate = (float)($request->input('tax_rate', 10.00));
            $taxableAmount = max(0, $subtotal - $discountAmount);
            $taxAmount = round(($taxableAmount * $taxRate) / 100, 2);
            $totalAmount = max(0, $taxableAmount + $taxAmount);

            $paymentMethod = $request->input('payment_method');
            $cashTendered = (float)($request->input('cash_tendered', 0));

            if ($paymentMethod === 'cash') {
                if ($cashTendered < $totalAmount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Uang tunai yang diterima kurang dari total tagihan!',
                    ], 422);
                }
                $changeDue = $cashTendered - $totalAmount;
            } else {
                $cashTendered = $totalAmount;
                $changeDue = 0;
            }

            // Decrement stock for purchased items
            $updatedStocks = [];
            foreach ($productsToDecrement as $entry) {
                $entry['product']->decrement('stock', $entry['qty']);
                $updatedStocks[] = [
                    'id' => $entry['product']->id,
                    'stock' => (int)$entry['product']->fresh()->stock,
                ];
            }

            $orderCreateStartTime = microtime(true);

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_name' => $request->input('customer_name') ?: 'Pelanggan Umum',
                'table_number' => $request->input('table_number') ?: ($request->input('order_type') === 'take_away' ? 'Take Away' : 'Meja 01'),
                'order_type' => $request->input('order_type'),
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'cash_tendered' => $cashTendered,
                'change_due' => $changeDue,
                'kitchen_status' => 'pending',
                'cashier_id' => Auth::id(),
                'notes' => $request->input('notes'),
            ]);

            foreach ($orderItemsToCreate as $itemData) {
                $order->items()->create($itemData);
            }

            // Refresh loaded items and cashier relations so receipt services have full data
            $order->load(['items.product', 'cashier']);

            // Log Order Created
            Log::info(sprintf(
                "[ORDER CREATED] Order #%s created | Items: %d | Total: Rp %s | Cashier: %s | Type: %s",
                $order->order_number,
                $order->items->count(),
                number_format($order->total_amount, 0, ',', '.'),
                $order->cashier?->name ?: 'Kasir POS',
                strtoupper((string)$order->order_type)
            ));

            // Realtime Update Signals:
            // 1. Update cache timestamp for SSE long polling / stream
            $updateTime = microtime(true);
            Cache::put('kds_last_update', $updateTime, 3600);
            Cache::put('kds_last_order_id', $order->id, 3600);

            // 2. Dispatch Laravel broadcast event
            event(new NewOrderCreated($order));

            // Generate Thermal Receipt ESC/POS and Plaintext
            $printerSettings = PrinterSetting::instance();
            $receiptBytes = $this->escPosService->generateReceiptBytes($order, $printerSettings);
            $receiptPlainText = $this->escPosService->generateReceiptPlainText($order, $printerSettings);
            $kitchenTicketBytes = $this->escPosService->generateKitchenTicketBytes($order, $printerSettings);

            // Direct Hardware Print to Putian POS Thermal
            $directPrintResult = null;
            if ($printerSettings->auto_print_cashier && $printerSettings->direct_hardware_print) {
                $tPrintStart = microtime(true);
                $directPrintResult = $this->escPosService->printDirectToWindowsPrinter(
                    $receiptBytes, 
                    $printerSettings->windows_printer_name
                );
                $tPrintEnd = microtime(true);
                $printDurationMs = (int) round(($tPrintEnd - $tPrintStart) * 1000);

                // Record print latency from order created until print completed
                $printLog = $this->escPosService->recordPrintLog(
                    $order,
                    'auto_hardware',
                    $directPrintResult,
                    $orderCreateStartTime,
                    $printDurationMs,
                    Auth::user()
                );

                $directPrintResult['latency_ms'] = $printLog->latency_ms;
                $directPrintResult['duration_ms'] = $printLog->duration_ms;
                $directPrintResult['printed_at'] = $printLog->printed_at?->toIso8601String();
            }

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat & dikirim ke Dapur!',
                'order' => $order->fresh(['items', 'cashier']),
                'updated_stocks' => $updatedStocks,
                'receipt' => [
                    'plain_text' => $receiptPlainText,
                    'escpos_base64' => base64_encode($receiptBytes),
                    'kitchen_ticket_base64' => base64_encode($kitchenTicketBytes),
                    'paper_size' => $printerSettings->paper_size,
                    'auto_print' => $printerSettings->auto_print_cashier,
                    'direct_hardware_print' => $printerSettings->direct_hardware_print,
                    'direct_print_result' => $directPrintResult,
                    'latency_ms' => $directPrintResult['latency_ms'] ?? null,
                ],
            ]);
        });
    }
}
