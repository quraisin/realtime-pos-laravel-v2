<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PosSystemTest extends TestCase
{
    private User $cashier;
    private Product $sampleProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::where('username', 'cashier@admin')->first();
        $this->sampleProduct = Product::where('is_available', true)->first();
    }

    public function test_unauthenticated_user_cannot_access_pos(): void
    {
        $response = $this->get('/pos');
        $response->assertRedirect('/login');
    }

    public function test_cashier_can_retrieve_categories_and_products(): void
    {
        $catResponse = $this->actingAs($this->cashier)->getJson('/api/pos/categories');
        $catResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['categories' => [['id', 'name', 'slug']]]);

        $prodResponse = $this->actingAs($this->cashier)->getJson('/api/pos/products');
        $prodResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['products' => [['id', 'name', 'price', 'stock']]]);
    }

    public function test_cashier_can_create_order_with_realtime_event_and_stock_reduction(): void
    {
        Event::fake([NewOrderCreated::class]);

        $initialStock = $this->sampleProduct->stock;
        $orderQty = 2;

        $payload = [
            'customer_name' => 'Test Pelanggan POS',
            'table_number' => 'Meja 08',
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'cash_tendered' => 200000,
            'discount_amount' => 5000,
            'tax_rate' => 10.00,
            'notes' => 'Tingkat kepedasan sedang',
            'items' => [
                [
                    'product_id' => $this->sampleProduct->id,
                    'quantity' => $orderQty,
                    'notes' => 'Jangan pakai micin',
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/orders', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'customer_name' => 'Test Pelanggan POS',
                    'kitchen_status' => 'pending',
                ],
            ])
            ->assertJsonStructure([
                'receipt' => ['plain_text', 'escpos_base64', 'kitchen_ticket_base64', 'paper_size'],
            ]);

        // Assert Stock Decremented
        $this->assertEquals($initialStock - $orderQty, $this->sampleProduct->fresh()->stock);

        // Assert Event Dispatched
        Event::assertDispatched(NewOrderCreated::class, function ($event) {
            return $event->order->customer_name === 'Test Pelanggan POS';
        });

        // Assert KDS Cache updated
        $this->assertNotNull(Cache::get('kds_last_update'));
    }

    public function test_cashier_cannot_checkout_cash_with_insufficient_payment(): void
    {
        $payload = [
            'customer_name' => 'Pelanggan Kurang Uang',
            'table_number' => 'Meja 01',
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'cash_tendered' => 1000, // Insufficient
            'items' => [
                [
                    'product_id' => $this->sampleProduct->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/orders', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cashier_cannot_order_item_when_stock_insufficient(): void
    {
        $this->sampleProduct->update(['stock' => 2]);

        $payload = [
            'customer_name' => 'Pelanggan Melebihi Stok',
            'table_number' => 'Meja 01',
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'cash_tendered' => 200000,
            'items' => [
                [
                    'product_id' => $this->sampleProduct->id,
                    'quantity' => 5, // Exceeds available stock of 2
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/orders', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Assert stock remains unchanged
        $this->assertEquals(2, $this->sampleProduct->fresh()->stock);
    }
}
