<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CmsSystemTest extends TestCase
{
    private User $superadmin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::where('email', 'adnan@superadmin.com')->first();
        $this->cashier = User::where('email', 'cashier@admin')->first();
    }

    public function test_superadmin_can_login_via_cms_login_portal(): void
    {
        $response = $this->postJson('/cms/login', [
            'username' => 'adnan@superadmin.com',
            'password' => 'Blessings777!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'redirect' => route('cms.index'),
            ]);

        $this->assertAuthenticatedAs($this->superadmin);
    }

    public function test_non_superadmin_rejected_from_cms_login(): void
    {
        $response = $this->postJson('/cms/login', [
            'username' => 'cashier@admin',
            'password' => 'behemoth',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertGuest();
    }

    public function test_unauthenticated_user_redirected_when_accessing_cms(): void
    {
        $response = $this->get('/cms');
        $response->assertRedirect(route('cms.login'));
    }

    public function test_cashier_cannot_access_cms_routes(): void
    {
        $response = $this->actingAs($this->cashier)->get('/cms');
        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_cms_dashboard(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/cms');
        $response->assertStatus(200);
        $response->assertSee('cms-root');
    }

    public function test_superadmin_can_manage_categories(): void
    {
        // 1. List categories
        $res = $this->actingAs($this->superadmin)->getJson('/api/cms/categories');
        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2. Create new category
        $createRes = $this->actingAs($this->superadmin)->postJson('/api/cms/categories', [
            'name' => 'Menu Spesial Chef',
            'icon' => 'flame',
            'color' => '#8b5cf6',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJson(['success' => true]);

        $newCatId = $createRes->json('category.id');

        // 3. Update category
        $updateRes = $this->actingAs($this->superadmin)->putJson("/api/cms/categories/{$newCatId}", [
            'name' => 'Menu Spesial Chef Updated',
            'color' => '#ec4899',
            'sort_order' => 6,
            'is_active' => true,
        ]);

        $updateRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // 4. Delete category
        $delRes = $this->actingAs($this->superadmin)->deleteJson("/api/cms/categories/{$newCatId}");
        $delRes->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_superadmin_can_manage_products_menu(): void
    {
        $category = Category::first();

        // 1. Create product
        $createRes = $this->actingAs($this->superadmin)->postJson('/api/cms/products', [
            'category_id' => $category->id,
            'name' => 'Sate Maranggi Spesial',
            'sku' => 'TEST-SAT-01',
            'price' => 50000,
            'cost_price' => 25000,
            'stock' => 40,
            'description' => 'Sate daging sapi empuk dengan bumbu maranggi manis gurih.',
            'is_available' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJson(['success' => true]);

        $prodId = $createRes->json('product.id');

        // 2. Toggle status
        $toggleRes = $this->actingAs($this->superadmin)->patchJson("/api/cms/products/{$prodId}/toggle-status");
        $toggleRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'product' => ['is_available' => false],
            ]);

        // 3. Update stock
        $stockRes = $this->actingAs($this->superadmin)->patchJson("/api/cms/products/{$prodId}/stock", [
            'stock' => 88,
        ]);
        $stockRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'product' => ['stock' => 88],
            ]);

        // 4. Update details
        $updateRes = $this->actingAs($this->superadmin)->putJson("/api/cms/products/{$prodId}", [
            'category_id' => $category->id,
            'name' => 'Sate Maranggi Spesial Extra',
            'sku' => 'TEST-SAT-01',
            'price' => 55000,
            'cost_price' => 28000,
            'stock' => 90,
            'is_available' => true,
        ]);
        $updateRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // 5. Delete product
        $delRes = $this->actingAs($this->superadmin)->deleteJson("/api/cms/products/{$prodId}");
        $delRes->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_superadmin_can_retrieve_deep_cms_analytics(): void
    {
        $response = $this->actingAs($this->superadmin)->getJson('/api/cms/analytics?period=7days');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'kpis' => [
                    'total_revenue',
                    'total_cost',
                    'net_profit',
                    'profit_margin_pct',
                    'total_orders',
                    'average_order_value',
                    'total_items_sold',
                ],
                'top_products',
                'category_breakdown',
                'daily_data',
                'hourly_data',
                'payment_methods',
                'recent_transactions',
            ]);
    }

    public function test_qurais_kitchen_brand_and_cashier_name(): void
    {
        $settings = PrinterSetting::instance();
        $this->assertEquals('Qurais Kitchen', $settings->store_name);

        $cashierUser = User::where('username', 'cashier@admin')->first();
        $this->assertEquals('Qurais Kitchen', $cashierUser->name);
    }

    public function test_superadmin_can_access_all_features_including_pos_and_kds(): void
    {
        // 1. Superadmin can access Dashboard
        $this->actingAs($this->superadmin)->get('/cms')->assertStatus(200);

        // 2. Superadmin can access POS Cashier
        $this->actingAs($this->superadmin)->get('/pos')->assertStatus(200);

        // 3. Superadmin can access KDS Kitchen
        $this->actingAs($this->superadmin)->get('/kds')->assertStatus(200);

        // 4. Superadmin can access Transactions
        $this->actingAs($this->superadmin)->get('/transactions')->assertStatus(200);

        // 5. Superadmin can access Analytics
        $this->actingAs($this->superadmin)->get('/analytics')->assertStatus(200);
    }

    public function test_cashier_and_kitchen_strictly_forbidden_from_dashboard(): void
    {
        $kitchen = User::where('username', 'chef@admin')->first();

        // Cashier cannot access CMS web routes or CMS APIs
        $this->actingAs($this->cashier)->get('/cms')->assertStatus(403);
        $this->actingAs($this->cashier)->get('/cms/products')->assertStatus(403);
        $this->actingAs($this->cashier)->get('/cms/categories')->assertStatus(403);
        $this->actingAs($this->cashier)->getJson('/api/cms/categories')->assertStatus(403);
        $this->actingAs($this->cashier)->getJson('/api/cms/products')->assertStatus(403);
        $this->actingAs($this->cashier)->getJson('/api/cms/analytics')->assertStatus(403);

        // Kitchen cannot access CMS web routes or CMS APIs
        $this->actingAs($kitchen)->get('/cms')->assertStatus(403);
        $this->actingAs($kitchen)->get('/cms/products')->assertStatus(403);
        $this->actingAs($kitchen)->get('/cms/categories')->assertStatus(403);
        $this->actingAs($kitchen)->getJson('/api/cms/categories')->assertStatus(403);
        $this->actingAs($kitchen)->getJson('/api/cms/products')->assertStatus(403);
        $this->actingAs($kitchen)->getJson('/api/cms/analytics')->assertStatus(403);
    }
}
