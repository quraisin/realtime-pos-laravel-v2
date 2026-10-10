<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load(['items', 'cashier']);
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
        return 'new.order';
    }

    /**
     * Get broadcast data
     */
    public function broadcastWith(): array
    {
        return [
            'order' => $this->order->toArray(),
            'timestamp' => now()->toISOString(),
            'message' => 'Pesanan baru ' . $this->order->order_number . ' diterima!',
        ];
    }
}
