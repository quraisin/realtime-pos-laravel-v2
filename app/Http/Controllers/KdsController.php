<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Services\EscPosReceiptService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KdsController extends Controller
{
    protected EscPosReceiptService $escPosService;

    public function __construct(EscPosReceiptService $escPosService)
    {
        $this->escPosService = $escPosService;
    }

    /**
     * Show Kitchen Display System (KDS) View
     */
    public function index(): View
    {
        return view('kds.index', [
            'printerSettings' => PrinterSetting::instance(),
        ]);
    }

    /**
     * Get active kitchen orders
     */
    public function getOrders(): JsonResponse
    {
        $cutoffTime = Carbon::now()->subHours(3);

        $orders = Order::with(['items', 'cashier'])
            ->where(function ($q) use ($cutoffTime) {
                // Show active orders (pending, preparing, ready)
                $q->whereIn('kitchen_status', ['pending', 'preparing', 'ready'])
                  // Or completed orders within the last 3 hours
                  ->orWhere(function ($q2) use ($cutoffTime) {
                      $q2->where('kitchen_status', 'completed')
                         ->where('updated_at', '>=', $cutoffTime);
                  });
            })
            ->where('payment_status', '!=', 'void')
            ->orderByRaw("
                CASE 
                    WHEN kitchen_status = 'pending' THEN 1
                    WHEN kitchen_status = 'preparing' THEN 2
                    WHEN kitchen_status = 'ready' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'timestamp' => microtime(true),
            'counts' => [
                'pending' => $orders->where('kitchen_status', 'pending')->count(),
                'preparing' => $orders->where('kitchen_status', 'preparing')->count(),
                'ready' => $orders->where('kitchen_status', 'ready')->count(),
                'completed' => $orders->where('kitchen_status', 'completed')->count(),
            ],
        ]);
    }

    /**
     * Advance order status (pending -> preparing -> ready -> completed)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,completed,cancelled',
        ]);

        $order = Order::with('items')->findOrFail($id);
        $newStatus = $request->input('status');
        $prevStatus = $order->kitchen_status;

        $updates = ['kitchen_status' => $newStatus];
        $now = now();

        if ($newStatus === 'preparing' && !$order->kitchen_started_at) {
            $updates['kitchen_started_at'] = $now;
        } elseif ($newStatus === 'ready' && !$order->kitchen_ready_at) {
            $updates['kitchen_ready_at'] = $now;
            // Mark all items as prepared automatically
            $order->items()->update(['is_prepared' => true]);
        } elseif ($newStatus === 'completed' && !$order->kitchen_completed_at) {
            $updates['kitchen_completed_at'] = $now;
            $order->items()->update(['is_prepared' => true]);
        }

        $order->update($updates);

        // Signal SSE & Broadcast
        Cache::put('kds_last_update', microtime(true), 3600);
        event(new OrderStatusUpdated($order, $prevStatus));

        return response()->json([
            'success' => true,
            'message' => "Status pesanan #{$order->order_number} diperbarui menjadi " . strtoupper($newStatus),
            'order' => $order->fresh(['items', 'cashier']),
        ]);
    }

    /**
     * Toggle item checklist in KDS
     */
    public function toggleItemPrepared(Request $request, int $itemId): JsonResponse
    {
        $item = OrderItem::with('order')->findOrFail($itemId);
        $item->is_prepared = !$item->is_prepared;
        $item->save();

        // Check if all items in the order are prepared
        $order = $item->order;
        $allItemsPrepared = $order->items()->where('is_prepared', false)->count() === 0;

        if ($allItemsPrepared && $order->kitchen_status === 'preparing') {
            $order->update([
                'kitchen_status' => 'ready',
                'kitchen_ready_at' => now(),
            ]);
        }

        Cache::put('kds_last_update', microtime(true), 3600);
        event(new OrderStatusUpdated($order, 'checklist_updated'));

        return response()->json([
            'success' => true,
            'item' => $item,
            'order' => $order->fresh(['items', 'cashier']),
        ]);
    }

    /**
     * Server-Sent Events (SSE) Realtime Stream for KDS
     */
    public function stream(Request $request): StreamedResponse
    {
        $clientLastSeen = (float)$request->query('last_seen', 0);

        return response()->stream(function () use ($clientLastSeen) {
            // Release session lock immediately so other requests from this client are not blocked
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            // Disable output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $lastChecked = $clientLastSeen;
            $startTime = time();
            $maxExecution = 15; // Reconnect interval for SSE

            // Send initial ping
            echo "event: ping\ndata: " . json_encode(['connected' => true, 'time' => microtime(true)]) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            while (true) {
                if (connection_aborted() || (time() - $startTime) >= $maxExecution) {
                    break;
                }

                $serverLastUpdate = (float)Cache::get('kds_last_update', 0);

                if ($serverLastUpdate > $lastChecked) {
                    $lastChecked = $serverLastUpdate;

                    $cutoffTime = Carbon::now()->subHours(3);
                    $activeOrders = Order::with(['items', 'cashier'])
                        ->where(function ($q) use ($cutoffTime) {
                            $q->whereIn('kitchen_status', ['pending', 'preparing', 'ready'])
                              ->orWhere(function ($q2) use ($cutoffTime) {
                                  $q2->where('kitchen_status', 'completed')
                                     ->where('updated_at', '>=', $cutoffTime);
                              });
                        })
                        ->where('payment_status', '!=', 'void')
                        ->orderByRaw("
                            CASE 
                                WHEN kitchen_status = 'pending' THEN 1
                                WHEN kitchen_status = 'preparing' THEN 2
                                WHEN kitchen_status = 'ready' THEN 3
                                ELSE 4
                            END
                        ")
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $payload = [
                        'type' => 'orders_update',
                        'timestamp' => $serverLastUpdate,
                        'orders' => $activeOrders,
                        'counts' => [
                            'pending' => $activeOrders->where('kitchen_status', 'pending')->count(),
                            'preparing' => $activeOrders->where('kitchen_status', 'preparing')->count(),
                            'ready' => $activeOrders->where('kitchen_status', 'ready')->count(),
                            'completed' => $activeOrders->where('kitchen_status', 'completed')->count(),
                        ],
                    ];

                    echo "event: update\ndata: " . json_encode($payload) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                } else {
                    // Send heartbeat comment to keep connection alive
                    echo ": heartbeat\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }

                // Sleep for 1 second before checking again
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
