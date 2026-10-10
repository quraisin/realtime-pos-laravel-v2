<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Show Transaction Analytics Dashboard view
     */
    public function index(): View
    {
        return view('analytics.index');
    }

    /**
     * Get transaction analytics summary and chart datasets
     */
    public function getSummary(Request $request): JsonResponse
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

        $baseOrdersQuery = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Key KPI Metrics
        $totalRevenue = (float)$baseOrdersQuery->sum('total_amount');
        $totalOrders = (int)$baseOrdersQuery->count();
        $averageOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        // Total items sold in period
        $totalItemsSold = (int)OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->where('payment_status', 'paid')
              ->whereBetween('created_at', [$startDate, $endDate]);
        })->sum('quantity');

        // Kitchen Performance: Average prep time in minutes for completed orders
        $completedWithPrep = (clone $baseOrdersQuery)
            ->whereNotNull('kitchen_started_at')
            ->whereNotNull('kitchen_ready_at')
            ->get();

        $avgPrepMinutes = 0;
        if ($completedWithPrep->count() > 0) {
            $totalMinutes = $completedWithPrep->reduce(function ($carry, $order) {
                return $carry + $order->kitchen_started_at->diffInMinutes($order->kitchen_ready_at);
            }, 0);
            $avgPrepMinutes = round($totalMinutes / $completedWithPrep->count(), 1);
        }

        // Payment Method Breakdown
        $paymentMethods = (clone $baseOrdersQuery)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($row) use ($totalRevenue) {
                return [
                    'method' => $row->payment_method,
                    'label' => strtoupper($row->payment_method),
                    'count' => (int)$row->count,
                    'total' => (float)$row->total,
                    'percentage' => $totalRevenue > 0 ? round(($row->total / $totalRevenue) * 100, 1) : 0,
                ];
            });

        // Order Type Breakdown
        $orderTypes = (clone $baseOrdersQuery)
            ->select('order_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('order_type')
            ->get()
            ->map(function ($row) {
                return [
                    'type' => $row->order_type,
                    'label' => ucwords(str_replace('_', ' ', $row->order_type)),
                    'count' => (int)$row->count,
                    'total' => (float)$row->total,
                ];
            });

        // Top 5 Best Selling Products
        $topProducts = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->where('payment_status', 'paid')
              ->whereBetween('created_at', [$startDate, $endDate]);
        })
        ->select('product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_sales'))
        ->groupBy('product_name')
        ->orderByDesc('total_qty')
        ->limit(5)
        ->get();

        // Hourly Sales Breakdown (00 - 23)
        $hourlyOrders = (clone $baseOrdersQuery)->get();
        $hourlyData = [];
        for ($h = 8; $h <= 22; $h++) {
            $hourLabel = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
            $inHour = $hourlyOrders->filter(function ($order) use ($h) {
                return (int)$order->created_at->format('H') === $h;
            });

            $hourlyData[] = [
                'hour' => $hourLabel,
                'revenue' => (float)$inHour->sum('total_amount'),
                'orders' => (int)$inHour->count(),
            ];
        }

        // Daily Trend for the period
        $dailyData = [];
        $cursor = $startDate->copy()->startOfDay();
        while ($cursor->lte($endDate)) {
            $dayStr = $cursor->format('Y-m-d');
            $dayLabel = $cursor->format('d M');

            $inDay = $hourlyOrders->filter(function ($order) use ($dayStr) {
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

        return response()->json([
            'success' => true,
            'period' => $period,
            'kpis' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'average_order_value' => $averageOrderValue,
                'total_items_sold' => $totalItemsSold,
                'avg_kitchen_prep_minutes' => $avgPrepMinutes,
            ],
            'payment_methods' => $paymentMethods,
            'order_types' => $orderTypes,
            'top_products' => $topProducts,
            'hourly_data' => $hourlyData,
            'daily_data' => $dailyData,
        ]);
    }
}
