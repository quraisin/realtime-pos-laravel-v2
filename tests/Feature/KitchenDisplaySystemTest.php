<?php

namespace Tests\Feature;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class KitchenDisplaySystemTest extends TestCase
{
    private User $chef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chef = User::where('username', 'chef@admin')->first();
        if (Order::count() === 0) {
            $this->createTestOrder(['kitchen_status' => 'pending']);
        }
    }

    public function test_chef_can_retrieve_active_kitchen_orders(): void
    {
        $response = $this->actingAs($this->chef)->getJson('/api/kds/orders');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'orders' => [['id', 'order_number', 'kitchen_status', 'items']],
                'counts' => ['pending', 'preparing', 'ready', 'completed'],
            ]);
    }

    public function test_chef_can_advance_order_status_to_preparing_and_ready(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $order = Order::where('kitchen_status', 'pending')->first();
        if (!$order) {
            $order = Order::first();
            $order->update(['kitchen_status' => 'pending']);
        }

        // 1. Advance to preparing
        $response1 = $this->actingAs($this->chef)->postJson("/api/kds/orders/{$order->id}/status", [
            'status' => 'preparing',
        ]);

        $response1->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertEquals('preparing', $order->fresh()->kitchen_status);
        $this->assertNotNull($order->fresh()->kitchen_started_at);

        // 2. Advance to ready
        $response2 = $this->actingAs($this->chef)->postJson("/api/kds/orders/{$order->id}/status", [
            'status' => 'ready',
        ]);

        $response2->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertEquals('ready', $order->fresh()->kitchen_status);
        $this->assertNotNull($order->fresh()->kitchen_ready_at);

        Event::assertDispatched(OrderStatusUpdated::class);
    }

    public function test_chef_can_toggle_item_checklist_in_kds(): void
    {
        $orderItem = OrderItem::first();
        $initialState = $orderItem->is_prepared;

        $response = $this->actingAs($this->chef)->postJson("/api/kds/items/{$orderItem->id}/toggle");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(!$initialState, $orderItem->fresh()->is_prepared);
    }

    public function test_kds_sse_stream_returns_event_stream_headers(): void
    {
        $response = $this->actingAs($this->chef)->get('/api/kds/stream');

        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    public function test_chef_can_get_individual_menu_item_kitchen_ticket(): void
    {
        $orderItem = OrderItem::first();

        $response = $this->actingAs($this->chef)->getJson("/api/items/{$orderItem->id}/kitchen-ticket");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'type' => 'kitchen_item'])
            ->assertJsonStructure(['item_id', 'product_name', 'plain_text', 'escpos_base64']);
    }

    public function test_chef_can_print_individual_menu_item_ticket_direct(): void
    {
        $orderItem = OrderItem::first();

        $response = $this->actingAs($this->chef)->postJson("/api/printer/print-item/{$orderItem->id}", [
            'printer_name' => 'Putian POS Thermal 2',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'printer', 'item_name', 'order_number']);
    }
}
