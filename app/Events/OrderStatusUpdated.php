<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $previousStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, string $previousStatus = '')
    {
        $this->order = $order->load(['items', 'cashier']);
        $this->previousStatus = $previousStatus;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('kitchen-orders'),
            new Channel('pos-transactions'),
        ];
    }

    /**
     * Broadcast as name
     */
    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    /**
     * Get broadcast data
     */
    public function broadcastWith(): array
    {
        return [
            'order' => $this->order->toArray(),
            'previous_status' => $this->previousStatus,
            'kitchen_status' => $this->order->kitchen_status,
            'timestamp' => now()->toISOString(),
        ];
    }
}
