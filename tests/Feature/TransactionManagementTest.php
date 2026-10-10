<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class TransactionManagementTest extends TestCase
{
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::where('username', 'cashier@admin')->first();
        if (Order::count() === 0) {
            $this->createTestOrder();
        }
    }

    public function test_cashier_can_retrieve_paginated_transactions_and_summary(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'orders' => ['data', 'current_page', 'total'],
                'summary' => ['total_revenue', 'total_orders', 'paid_orders'],
            ]);
    }

    public function test_cashier_can_view_order_detail_with_receipt(): void
    {
        $order = Order::first();

        $response = $this->actingAs($this->cashier)->getJson("/api/transactions/{$order->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'order' => ['id', 'order_number', 'items'],
                'receipt' => ['plain_text', 'escpos_base64'],
            ]);
    }

    public function test_cashier_can_void_order_and_restore_inventory_stock(): void
    {
        $product = Product::first();
        $initialStock = $product->stock;
        $orderQty = 2;

        // Create a temporary paid order to void
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Void Test Customer',
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => $product->price * $orderQty,
            'total_amount' => $product->price * $orderQty,
            'kitchen_status' => 'pending',
            'cashier_id' => $this->cashier->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => $orderQty,
            'subtotal' => $product->price * $orderQty,
        ]);

        $response = $this->actingAs($this->cashier)->postJson("/api/transactions/{$order->id}/void", [
            'reason' => 'Customer changed mind',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('void', $order->fresh()->payment_status);
        $this->assertEquals('cancelled', $order->fresh()->kitchen_status);

        // Stock restored
        $this->assertEquals($initialStock + $orderQty, $product->fresh()->stock);
    }

    public function test_cashier_can_export_transactions_as_csv(): void
    {
        $response = $this->actingAs($this->cashier)->get('/api/transactions-export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_cashier_can_update_item_note(): void
    {
        $order = Order::with('items')->first();
        $item = $order->items->first();

        $response = $this->actingAs($this->cashier)->postJson("/api/transactions/items/{$item->id}/note", [
            'notes' => 'Ekstra pedas, tanpa seledri, kuah dipisah',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('Ekstra pedas, tanpa seledri, kuah dipisah', $item->fresh()->notes);
    }

    public function test_cashier_can_update_order_notes(): void
    {
        $order = Order::first();

        $response = $this->actingAs($this->cashier)->postJson("/api/transactions/{$order->id}/notes", [
            'notes' => 'Tamu VIP meja depan',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('Tamu VIP meja depan', $order->fresh()->notes);
    }
}
