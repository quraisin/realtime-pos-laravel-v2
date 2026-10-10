<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class ThermalPrinterTest extends TestCase
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

    public function test_can_get_and_update_printer_settings(): void
    {
        $getResponse = $this->actingAs($this->cashier)->getJson('/api/printer/settings');
        $getResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['settings' => ['paper_size', 'store_name']]);

        $updateResponse = $this->actingAs($this->cashier)->postJson('/api/printer/settings', [
            'store_name' => 'BEHEMOTH UPDATED',
            'store_tagline' => 'Best Coffee & Food',
            'store_address' => 'Jl. Baru No. 1',
            'store_phone' => '089999999',
            'paper_size' => '80mm',
            'receipt_footer' => 'Sampai jumpa lagi!',
            'auto_print_cashier' => true,
            'auto_print_kitchen' => true,
            'cut_paper' => true,
            'open_cash_drawer' => true,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'settings' => [
                    'store_name' => 'BEHEMOTH UPDATED',
                    'paper_size' => '80mm',
                ],
            ]);
    }

    public function test_can_generate_test_print_payload(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/printer/test');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['escpos_base64', 'paper_size']);
    }

    public function test_can_download_binary_escpos_file(): void
    {
        $order = Order::first();

        $response = $this->actingAs($this->cashier)->get("/api/orders/{$order->id}/escpos-download");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_can_get_installed_printers(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/api/printer/installed-printers');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['printers']);
    }

    public function test_can_print_order_direct_to_windows_printer(): void
    {
        $order = Order::first();

        $response = $this->actingAs($this->cashier)->postJson("/api/printer/print-order/{$order->id}", [
            'printer_name' => 'Putian POS Thermal 2',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'printer', 'order_number']);
    }
}
