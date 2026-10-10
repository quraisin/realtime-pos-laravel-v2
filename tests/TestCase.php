<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function createTestOrder(array $attributes = []): \App\Models\Order
    {
        $cashier = \App\Models\User::where('username', 'cashier@admin')->first();
        $product = \App\Models\Product::first();

        $order = \App\Models\Order::create(array_merge([
            'order_number' => 'TRX-' . date('Ymd') . '-' . rand(1000, 9999),
            'customer_name' => 'Test Customer',
            'table_number' => 'Meja 01',
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 50000,
            'tax_rate' => 10.0,
            'tax_amount' => 5000,
            'discount_amount' => 0,
            'total_amount' => 55000,
            'cash_tendered' => 60000,
            'change_due' => 5000,
            'kitchen_status' => 'pending',
            'cashier_id' => $cashier?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        if ($product) {
            \App\Models\OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $product->price,
                'quantity' => 1,
                'subtotal' => $product->price,
                'is_prepared' => false,
            ]);
        }

        return $order->fresh(['items', 'cashier']);
    }
}
