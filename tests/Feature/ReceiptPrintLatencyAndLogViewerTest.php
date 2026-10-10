<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PrinterSetting;
use App\Models\Product;
use App\Models\ReceiptPrintLog;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReceiptPrintLatencyAndLogViewerTest extends TestCase
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

    public function test_order_creation_with_auto_print_records_latency_and_logs(): void
    {
        $settings = PrinterSetting::instance();
        $settings->update([
            'auto_print_cashier' => true,
            'direct_hardware_print' => true,
            'windows_printer_name' => 'Putian POS Thermal 2',
        ]);

        $product = Product::first();

        $payload = [
            'order_type' => 'dine_in',
            'customer_name' => 'Budi Tester',
            'table_number' => 'Meja 09',
            'payment_method' => 'cash',
            'cash_tendered' => 100000,
            'discount_amount' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => $product->price,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/orders', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'order' => ['id', 'order_number', 'receipt_printed_at', 'receipt_print_latency_ms'],
                'receipt' => ['direct_print_result', 'latency_ms'],
            ]);

        $orderId = $response->json('order.id');
        $order = Order::find($orderId);

        $this->assertNotNull($order->receipt_printed_at);
        $this->assertNotNull($order->receipt_print_latency_ms);
        $this->assertGreaterThan(0, $order->receipt_print_latency_ms);
        $this->assertEquals('auto_hardware', $order->receipt_print_method);

        // Verify receipt print logs table entry
        $this->assertDatabaseHas('receipt_print_logs', [
            'order_id' => $order->id,
            'print_method' => 'auto_hardware',
            'printer_name' => 'Putian POS Thermal 2',
        ]);
    }

    public function test_manual_print_records_latency_and_returns_in_response(): void
    {
        $order = Order::first();

        $response = $this->actingAs($this->cashier)->postJson("/api/printer/print-order/{$order->id}", [
            'printer_name' => 'Putian POS Thermal 2',
            'type' => 'receipt',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'printer', 'order_number', 'latency_ms', 'duration_ms', 'printed_at']);

        $latency = $response->json('latency_ms');
        $this->assertIsInt($latency);
        $this->assertGreaterThan(0, $latency);

        $this->assertDatabaseHas('receipt_print_logs', [
            'order_id' => $order->id,
            'print_method' => 'manual_hardware',
        ]);
    }

    public function test_client_web_serial_print_latency_endpoint(): void
    {
        $order = Order::first();

        $response = $this->actingAs($this->cashier)->postJson("/api/printer/record-print-latency/{$order->id}", [
            'method' => 'web_serial',
            'duration_ms' => 140,
            'success' => true,
            'printer' => 'USB Web Serial Thermal',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['latency_ms', 'printed_at', 'order_number']);

        $this->assertDatabaseHas('receipt_print_logs', [
            'order_id' => $order->id,
            'print_method' => 'web_serial',
            'duration_ms' => 140,
        ]);
    }

    public function test_logs_route_redirects_to_opcodes_log_viewer(): void
    {
        $response = $this->actingAs($this->cashier)->get('/logs');
        $response->assertRedirect(route('log-viewer.index'));
    }

    public function test_opcodes_log_viewer_dashboard_accessible_by_cashier(): void
    {
        Log::info('[ORDER CREATED] Order #TRX-TEST-0001 created | Items: 1 | Total: Rp 50.000 | Cashier: Kasir Utama');
        Log::info('[RECEIPT PRINTED] Order #TRX-TEST-0001 | Latency from Order Created: 125 ms (0.13 s) | Print Duration: 40 ms | Method: auto_hardware | Printer: Putian POS Thermal 2');

        $response = $this->actingAs($this->cashier)->get(route('log-viewer.index'));
        $response->assertStatus(200);
        $this->assertStringContainsString('Log Viewer', $response->getContent());
    }

    public function test_opcodes_log_viewer_api_files_and_logs_endpoints(): void
    {
        Log::info('[RECEIPT PRINTED] Order #TRX-API-0002 | Latency from Order Created: 195 ms (0.20 s) | Print Duration: 55 ms | Method: manual_hardware | Printer: Putian POS Thermal 2');

        $filesResponse = $this->actingAs($this->cashier)->getJson('/log-viewer/api/files');
        $filesResponse->assertStatus(200);

        $logsResponse = $this->actingAs($this->cashier)->getJson('/log-viewer/api/logs');
        $logsResponse->assertStatus(200);
        $logsResponse->assertJsonStructure(['logs']);
    }

    public function test_receipt_print_latency_is_logged_to_file(): void
    {
        $logPath = storage_path('logs/laravel.log');
        $this->assertTrue(File::exists($logPath));
        $content = File::get($logPath);
        $this->assertStringContainsString('[RECEIPT PRINTED]', $content);
        $this->assertStringContainsString('Latency from Order Created:', $content);
    }
}
