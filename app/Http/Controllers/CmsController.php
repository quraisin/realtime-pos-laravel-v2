<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CmsController extends Controller
{
    /**
     * Show CMS Dashboard view
     */
    public function index(Request $request): View
    {
        return view('cms.index', [
            'initialTab' => $request->query('tab', 'dashboard'),
            'user' => Auth::user(),
            'printerSettings' => PrinterSetting::instance(),
        ]);
    }

    /**
     * Show CMS Products / Menu view
     */
    public function products(Request $request): View
    {
        return view('cms.index', [
            'initialTab' => 'products',
            'user' => Auth::user(),
            'printerSettings' => PrinterSetting::instance(),
        ]);
    }

    /**
     * Show CMS Categories view
     */
    public function categories(Request $request): View
    {
        return view('cms.index', [
            'initialTab' => 'categories',
            'user' => Auth::user(),
            'printerSettings' => PrinterSetting::instance(),
        ]);
    }

    // =========================================================================
    // CATEGORY MANAGEMENT APIS
    // =========================================================================

    /**
     * Get all categories with product counts
     */
    public function getCategories(): JsonResponse
    {
        $categories = Category::withCount(['allProducts as total_products_count', 'products as active_products_count'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Create a new category
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Category::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $validated['icon'] = $validated['icon'] ?: 'utensils';
        $validated['color'] = $validated['color'] ?: '#ef4444';
        $validated['sort_order'] = $validated['sort_order'] ?? (Category::max('sort_order') + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $category = Category::create($validated);
        $category->loadCount(['allProducts as total_products_count', 'products as active_products_count']);

        return response()->json([
            'success' => true,
            'message' => "Kategori '{$category->name}' berhasil ditambahkan.",
            'category' => $category,
        ], 201);
    }

    /**
     * Update an existing category
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug,' . $category->id,
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);
        $category->loadCount(['allProducts as total_products_count', 'products as active_products_count']);

        return response()->json([
            'success' => true,
            'message' => "Kategori '{$category->name}' berhasil diperbarui.",
            'category' => $category,
        ]);
    }

    /**
     * Delete a category
     */
    public function deleteCategory(int $id): JsonResponse
    {
        $category = Category::withCount('allProducts')->findOrFail($id);

        if ($category->all_products_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat menghapus kategori '{$category->name}' karena masih memiliki {$category->all_products_count} menu terkait. Silakan pindahkan atau hapus menu terlebih dahulu.",
            ], 422);
        }

        $name = $category->name;
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => "Kategori '{$name}' berhasil dihapus.",
        ]);
    }

    // =========================================================================
    // MENU / PRODUCT MANAGEMENT APIS
    // =========================================================================

    /**
     * Get products with filters and category relations
     */
    public function getProducts(Request $request): JsonResponse
    {
        $query = Product::with('category');

        // Filter by category
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by availability
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'available') {
                $query->where('is_available', true);
            } elseif ($status === 'unavailable') {
                $query->where('is_available', false);
            }
        }

        // Search by name, SKU or description
        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('sku', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');

        if (in_array($sortBy, ['name', 'price', 'stock', 'created_at', 'sku'])) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'total' => $products->count(),
            'products' => $products,
        ]);
    }

    /**
     * Create a new product / menu item
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'image_url' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_available' => 'boolean',
        ]);

        if (empty($validated['sku'])) {
            $category = Category::find($validated['category_id']);
            $prefix = $category ? strtoupper(substr($category->slug, 0, 3)) : 'MNU';
            $validated['sku'] = $prefix . '-' . str_pad((string)(Product::max('id') + 1), 4, '0', STR_PAD_LEFT);
        }

        $validated['cost_price'] = $validated['cost_price'] ?? 0;
        $validated['stock'] = $validated['stock'] ?? 100;
        $validated['is_available'] = $validated['is_available'] ?? true;

        $product = Product::create($validated);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => "Menu '{$product->name}' berhasil ditambahkan ke Qurais Kitchen.",
            'product' => $product,
        ], 201);
    }

    /**
     * Show single product details
     */
    public function showProduct(int $id): JsonResponse
    {
        $product = Product::with('category')->findOrFail($id);

        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }

    /**
     * Update an existing product
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'sku' => 'required|string|max:50|unique:products,sku,' . $product->id,
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image_url' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_available' => 'boolean',
        ]);

        $validated['cost_price'] = $validated['cost_price'] ?? 0;
        $validated['is_available'] = $validated['is_available'] ?? true;

        $product->update($validated);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => "Menu '{$product->name}' berhasil diperbarui.",
            'product' => $product,
        ]);
    }

    /**
     * Quick toggle product availability
     */
    public function toggleProductStatus(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->is_available = !$product->is_available;
        $product->save();

        $statusLabel = $product->is_available ? 'Aktif (Tersedia)' : 'Nonaktif (Habis)';

        return response()->json([
            'success' => true,
            'message' => "Status menu '{$product->name}' diubah menjadi {$statusLabel}.",
            'product' => $product,
        ]);
    }

    /**
     * Quick update product stock
     */
    public function updateProductStock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'stock' => 'required|integer|min:0',
        ]);

        $product = Product::findOrFail($id);
        $product->stock = (int)$request->input('stock');
        $product->save();

        return response()->json([
            'success' => true,
            'message' => "Stok menu '{$product->name}' diperbarui menjadi {$product->stock}.",
            'product' => $product,
        ]);
    }

    /**
     * Delete a product
     */
    public function deleteProduct(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $name = $product->name;

        // Check if product is in order items
        $hasOrders = OrderItem::where('product_id', $product->id)->exists();
        if ($hasOrders) {
            // Nullify foreign key reference or soft deactivate so order histories are preserved
            OrderItem::where('product_id', $product->id)->update(['product_id' => null]);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => "Menu '{$name}' berhasil dihapus dari Qurais Kitchen.",
        ]);
    }

    // =========================================================================
    // TRANSACTION ANALYTICS APIS FOR CMS DASHBOARD
    // =========================================================================

    /**
     * Get deep transaction and sales analytics for Superadmin CMS
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $period = $request->input('period', '7days');
        $now = Carbon::now();

        $startDate = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'yesterday' => $now->copy()->subDay()->startOfDay(),
            '30days' => $now->copy()->subDays(29)->startOfDay(),
            'this_month' => $now->copy()->startOfMonth(),
            'all' => Carbon::createFromTimestamp(0),
            default => $now->copy()->subDays(6)->startOfDay(), // 7days
        };

        $endDate = match ($period) {
            'yesterday' => $now->copy()->subDay()->endOfDay(),
            default => $now->copy()->endOfDay(),
        };

        // Base paid orders query
        $paidOrdersQuery = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Key Financial & Sales KPIs
        $totalGrossRevenue = (float)$paidOrdersQuery->sum('total_amount');
        $totalOrdersCount = (int)$paidOrdersQuery->count();
        $averageOrderValue = $totalOrdersCount > 0 ? round($totalGrossRevenue / $totalOrdersCount, 2) : 0;

        // Voided & Refunded Orders
        $voidOrdersCount = (int)Order::whereIn('payment_status', ['void', 'refunded'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $voidOrdersAmount = (float)Order::whereIn('payment_status', ['void', 'refunded'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // Total Items sold
        $totalItemsSold = (int)OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->where('payment_status', 'paid')
              ->whereBetween('created_at', [$startDate, $endDate]);
        })->sum('quantity');

        // Estimated Cost & Net Profit
        // Sum(quantity * cost_price) from linked products
        $itemsWithCost = OrderItem::with('product')
            ->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->where('payment_status', 'paid')
                  ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->get();

        $totalEstimatedCost = $itemsWithCost->reduce(function ($carry, $item) {
            $costPrice = $item->product ? (float)$item->product->cost_price : ((float)$item->price * 0.45); // default fallback 45% cogs
            return $carry + ($costPrice * $item->quantity);
        }, 0.0);

        $totalNetProfit = max(0, $totalGrossRevenue - $totalEstimatedCost);
        $profitMarginPct = $totalGrossRevenue > 0 ? round(($totalNetProfit / $totalGrossRevenue) * 100, 1) : 0;

        // Trending / Top Selling Menus with Profit Margins
        $topProductsRaw = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->where('payment_status', 'paid')
              ->whereBetween('created_at', [$startDate, $endDate]);
        })
        ->select('product_name', 'product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_sales'))
        ->groupBy('product_name', 'product_id')
        ->orderByDesc('total_qty')
        ->limit(7)
        ->get();

        $topProducts = $topProductsRaw->map(function ($row) {
            $prod = $row->product_id ? Product::find($row->product_id) : null;
            $cost = $prod ? (float)$prod->cost_price : ((float)$row->total_sales * 0.45 / max(1, $row->total_qty));
            $totalCost = $cost * (int)$row->total_qty;
            $profit = max(0, (float)$row->total_sales - $totalCost);
            $margin = (float)$row->total_sales > 0 ? round(($profit / (float)$row->total_sales) * 100, 1) : 0;

            return [
                'product_name' => $row->product_name,
                'total_qty' => (int)$row->total_qty,
                'total_sales' => (float)$row->total_sales,
                'cost_price' => $cost,
                'profit' => $profit,
                'margin_pct' => $margin,
                'stock' => $prod ? (int)$prod->stock : null,
                'image_url' => $prod ? $prod->image_url : null,
            ];
        });

        // Category Sales Breakdown
        $categoryBreakdown = [];
        $categories = Category::all();
        foreach ($categories as $cat) {
            $catSales = OrderItem::whereHas('product', function ($q) use ($cat) {
                $q->where('category_id', $cat->id);
            })->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->where('payment_status', 'paid')
                  ->whereBetween('created_at', [$startDate, $endDate]);
            })->sum('subtotal');

            $catQty = OrderItem::whereHas('product', function ($q) use ($cat) {
                $q->where('category_id', $cat->id);
            })->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->where('payment_status', 'paid')
                  ->whereBetween('created_at', [$startDate, $endDate]);
            })->sum('quantity');

            $categoryBreakdown[] = [
                'category_id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color,
                'icon' => $cat->icon,
                'total_sales' => (float)$catSales,
                'total_qty' => (int)$catQty,
                'percentage' => $totalGrossRevenue > 0 ? round(((float)$catSales / $totalGrossRevenue) * 100, 1) : 0,
            ];
        }

        // Daily Revenue Trend
        $allOrdersInPeriod = (clone $paidOrdersQuery)->get();
        $dailyData = [];
        $cursor = $startDate->copy()->startOfDay();
        while ($cursor->lte($endDate)) {
            $dayStr = $cursor->format('Y-m-d');
            $dayLabel = $cursor->format('d M');

            $inDay = $allOrdersInPeriod->filter(function ($order) use ($dayStr) {
                return $order->created_at->format('Y-m-d') === $dayStr;
            });

            $dailyData[] = [
                'date' => $dayStr,
                'label' => $dayLabel,
                'revenue' => (float)$inDay->sum('total_amount'),
                'orders' => (int)$inDay->count(),
            ];

            $cursor->addDay();
        }

        // Hourly Sales Breakdown
        $hourlyData = [];
        for ($h = 8; $h <= 22; $h++) {
            $hourLabel = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
            $inHour = $allOrdersInPeriod->filter(function ($order) use ($h) {
                return (int)$order->created_at->format('H') === $h;
            });

            $hourlyData[] = [
                'hour' => $hourLabel,
                'revenue' => (float)$inHour->sum('total_amount'),
                'orders' => (int)$inHour->count(),
            ];
        }

        // Payment Methods Breakdown
        $paymentMethods = (clone $paidOrdersQuery)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($row) use ($totalGrossRevenue) {
                return [
                    'method' => $row->payment_method,
                    'label' => strtoupper($row->payment_method),
                    'count' => (int)$row->count,
                    'total' => (float)$row->total,
                    'percentage' => $totalGrossRevenue > 0 ? round(($row->total / $totalGrossRevenue) * 100, 1) : 0,
                ];
            });

        // Low stock products alert (stock <= 15)
        $lowStockProducts = Product::where('stock', '<=', 15)
            ->with('category')
            ->orderBy('stock', 'asc')
            ->limit(8)
            ->get();

        // Recent 10 Transactions
        $recentTransactions = Order::with('cashier')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'table_number' => $order->table_number,
                    'order_type' => $order->order_type,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'kitchen_status' => $order->kitchen_status,
                    'total_amount' => (float)$order->total_amount,
                    'cashier_name' => $order->cashier ? $order->cashier->name : 'Qurais Kitchen',
                    'created_at' => $order->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'period' => $period,
            'kpis' => [
                'total_revenue' => $totalGrossRevenue,
                'total_cost' => $totalEstimatedCost,
                'net_profit' => $totalNetProfit,
                'profit_margin_pct' => $profitMarginPct,
                'total_orders' => $totalOrdersCount,
                'average_order_value' => $averageOrderValue,
                'total_items_sold' => $totalItemsSold,
                'void_orders_count' => $voidOrdersCount,
                'void_orders_amount' => $voidOrdersAmount,
            ],
            'top_products' => $topProducts,
            'category_breakdown' => $categoryBreakdown,
            'daily_data' => $dailyData,
            'hourly_data' => $hourlyData,
            'payment_methods' => $paymentMethods,
            'low_stock_products' => $lowStockProducts,
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
