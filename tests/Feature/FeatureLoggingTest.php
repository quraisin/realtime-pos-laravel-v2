<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\PrinterSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\FeatureLog;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FeatureLoggingTest extends TestCase
{
    private User $cashier;
    private User $chef;
    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::where('username', 'cashier@admin')->first();
        $this->chef = User::where('username', 'chef@admin')->first();
        $this->superadmin = User::where('email', 'adnan@superadmin.com')->first();
    }

    public function test_feature_logger_writes_to_dedicated_channel_files(): void
    {
        // 1. Auth Log
        FeatureLog::auth('TEST_AUTH', 'Cashier testing auth log channel', ['test' => true]);
        $authLog = storage_path('logs/auth.log');
        $this->assertTrue(File::exists($authLog));
        $this->assertStringContainsString('[AUTH] [TEST_AUTH]', File::get($authLog));

        // 2. POS Log
        FeatureLog::pos('TEST_POS', 'Cashier testing pos log channel', ['test' => true]);
        $posLog = storage_path('logs/pos.log');
        $this->assertTrue(File::exists($posLog));
        $this->assertStringContainsString('[POS] [TEST_POS]', File::get($posLog));

        // 3. Transaction Log
        FeatureLog::transaction('TEST_TRX', 'Testing transaction log channel', ['test' => true]);
        $trxLog = storage_path('logs/transaction.log');
        $this->assertTrue(File::exists($trxLog));
        $this->assertStringContainsString('[TRANSACTION] [TEST_TRX]', File::get($trxLog));

        // 4. KDS Log
        FeatureLog::kds('TEST_KDS', 'Testing kds log channel', ['test' => true]);
        $kdsLog = storage_path('logs/kds.log');
        $this->assertTrue(File::exists($kdsLog));
        $this->assertStringContainsString('[KDS] [TEST_KDS]', File::get($kdsLog));

        // 5. CMS Log
        FeatureLog::cms('TEST_CMS', 'Testing cms log channel', ['test' => true]);
        $cmsLog = storage_path('logs/cms.log');
        $this->assertTrue(File::exists($cmsLog));
        $this->assertStringContainsString('[CMS] [TEST_CMS]', File::get($cmsLog));

        // 6. Printer Log
        FeatureLog::printer('TEST_PRINTER', 'Testing printer log channel', ['test' => true]);
        $printerLog = storage_path('logs/printer.log');
        $this->assertTrue(File::exists($printerLog));
        $this->assertStringContainsString('[PRINTER] [TEST_PRINTER]', File::get($printerLog));

        // 7. Analytics Log
        FeatureLog::analytics('TEST_ANALYTICS', 'Testing analytics log channel', ['test' => true]);
        $analyticsLog = storage_path('logs/analytics.log');
        $this->assertTrue(File::exists($analyticsLog));
        $this->assertStringContainsString('[ANALYTICS] [TEST_ANALYTICS]', File::get($analyticsLog));

        // Also verify main laravel.log received entries
        $laravelLog = storage_path('logs/laravel.log');
        $this->assertTrue(File::exists($laravelLog));
        $laravelContent = File::get($laravelLog);
        $this->assertStringContainsString('[AUTH] [TEST_AUTH]', $laravelContent);
        $this->assertStringContainsString('[POS] [TEST_POS]', $laravelContent);
        $this->assertStringContainsString('[TRANSACTION] [TEST_TRX]', $laravelContent);
        $this->assertStringContainsString('[KDS] [TEST_KDS]', $laravelContent);
        $this->assertStringContainsString('[CMS] [TEST_CMS]', $laravelContent);
        $this->assertStringContainsString('[PRINTER] [TEST_PRINTER]', $laravelContent);
    }

    public function test_auth_actions_are_logged_to_auth_channel(): void
    {
        // Failed login
        $this->postJson('/login', [
            'username' => 'nonexistent_user',
            'password' => 'wrongpass',
        ]);

        $authContent = File::get(storage_path('logs/auth.log'));
        $this->assertStringContainsString('[AUTH] [LOGIN_FAILED]', $authContent);

        // Successful login
        $this->postJson('/login', [
            'username' => 'cashier@admin',
            'password' => 'behemoth',
        ]);

        $authContent = File::get(storage_path('logs/auth.log'));
        $this->assertStringContainsString('[AUTH] [LOGIN_SUCCESS]', $authContent);
    }

    public function test_order_creation_is_logged_to_pos_channel(): void
    {
        $product = Product::first();

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/orders', [
            'order_type' => 'dine_in',
            'customer_name' => 'Bambang Log Test',
            'table_number' => 'Meja 88',
            'payment_method' => 'cash',
            'cash_tendered' => 100000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => $product->price,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $orderNumber = $response->json('order.order_number');

        $posContent = File::get(storage_path('logs/pos.log'));
        $this->assertStringContainsString('[POS] [ORDER_CREATED]', $posContent);
        $this->assertStringContainsString($orderNumber, $posContent);
    }

    public function test_kds_status_update_is_logged_to_kds_channel(): void
    {
        $order = $this->createTestOrder();

        $this->actingAs($this->chef)->postJson("/api/kds/orders/{$order->id}/status", [
            'status' => 'preparing',
        ]);

        $kdsContent = File::get(storage_path('logs/kds.log'));
        $this->assertStringContainsString('[KDS] [STATUS_UPDATED]', $kdsContent);
        $this->assertStringContainsString($order->order_number, $kdsContent);
    }

    public function test_void_order_is_logged_to_transaction_channel(): void
    {
        $order = $this->createTestOrder();

        $this->actingAs($this->cashier)->postJson("/api/transactions/{$order->id}/void", [
            'reason' => 'Customer changed their mind',
        ]);

        $trxContent = File::get(storage_path('logs/transaction.log'));
        $this->assertStringContainsString('[TRANSACTION] [ORDER_VOIDED]', $trxContent);
        $this->assertStringContainsString($order->order_number, $trxContent);
    }

    public function test_cms_operations_are_logged_to_cms_channel(): void
    {
        $response = $this->actingAs($this->superadmin)->postJson('/api/cms/categories', [
            'name' => 'Kategori Log Test',
            'icon' => 'utensils',
            'color' => '#10b981',
        ]);

        $response->assertStatus(201);

        $cmsContent = File::get(storage_path('logs/cms.log'));
        $this->assertStringContainsString('[CMS] [CATEGORY_CREATED]', $cmsContent);
        $this->assertStringContainsString('Kategori Log Test', $cmsContent);
    }

    public function test_log_viewer_discovers_all_feature_log_files(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/log-viewer/api/files');
        $response->assertStatus(200);

        $files = collect($response->json());
        $fileNames = $files->pluck('name')->toArray();

        $this->assertContains('auth.log', $fileNames);
        $this->assertContains('pos.log', $fileNames);
        $this->assertContains('transaction.log', $fileNames);
        $this->assertContains('kds.log', $fileNames);
        $this->assertContains('cms.log', $fileNames);
        $this->assertContains('printer.log', $fileNames);
    }
}
