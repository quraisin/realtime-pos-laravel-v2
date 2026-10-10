<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $permissions = [
            'pos.access',
            'transactions.access',
            'transactions.void',
            'analytics.access',
            'kds.access',
            'kds.update_status',
            'printer.settings',
            'cms.access',
            'cms.categories',
            'cms.products',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 2. Create Roles
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions($permissions);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashierRole->syncPermissions([
            'pos.access',
            'transactions.access',
            'transactions.void',
            'analytics.access',
            'printer.settings',
        ]);

        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $kitchenRole->syncPermissions([
            'kds.access',
            'kds.update_status',
        ]);

        // 3. Create Users
        // Superadmin: adnan@superadmin.com / Blessings777!
        $superadminUser = User::updateOrCreate(
            ['email' => 'adnan@superadmin.com'],
            [
                'name' => 'Adnan Superadmin',
                'username' => 'adnan@superadmin.com',
                'password' => Hash::make('Blessings777!'),
            ]
        );
        $superadminUser->syncRoles([$superadminRole]);

        // Cashier User: cashier@admin / behemoth (Renamed to Qurais Kitchen)
        $cashierUser = User::updateOrCreate(
            ['email' => 'cashier@admin'],
            [
                'name' => 'Qurais Kitchen',
                'username' => 'cashier@admin',
                'password' => Hash::make('behemoth'),
            ]
        );
        $cashierUser->syncRoles([$cashierRole]);

        // Kitchen User: chef@admin / behemoth
        $kitchenUser = User::updateOrCreate(
            ['email' => 'chef@admin'],
            [
                'name' => 'Head Chef Dapur',
                'username' => 'chef@admin',
                'password' => Hash::make('behemoth'),
            ]
        );
        $kitchenUser->syncRoles([$kitchenRole]);

        // 4. Create Printer Settings
        PrinterSetting::instance();

        // 5. Create Categories
        $categoriesData = [
            ['name' => 'Makanan Utama', 'slug' => 'makanan-utama', 'icon' => 'utensils', 'color' => '#ef4444', 'sort_order' => 1],
            ['name' => 'Minuman Segar', 'slug' => 'minuman-segar', 'icon' => 'coffee', 'color' => '#3b82f6', 'sort_order' => 2],
            ['name' => 'Snack & Camilan', 'slug' => 'snack-camilan', 'icon' => 'cookie', 'color' => '#f59e0b', 'sort_order' => 3],
            ['name' => 'Dessert Manis', 'slug' => 'dessert-manis', 'icon' => 'ice-cream', 'color' => '#ec4899', 'sort_order' => 4],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['slug']] = Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 6. Create Products
        $productsData = [
            // Makanan
            [
                'category_id' => $categories['makanan-utama']->id,
                'name' => 'Nasi Goreng Wagyu Spesial',
                'sku' => 'FOD-001',
                'price' => 45000,
                'cost_price' => 22000,
                'stock' => 100,
                'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=400&q=80',
                'description' => 'Nasi goreng harum dengan potongan daging wagyu tender, telur mata sapi, dan acar segar.',
            ],
            [
                'category_id' => $categories['makanan-utama']->id,
                'name' => 'Ayam Bakar Madu Pedas',
                'sku' => 'FOD-002',
                'price' => 38000,
                'cost_price' => 18000,
                'stock' => 85,
                'image_url' => 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?auto=format&fit=crop&w=400&q=80',
                'description' => 'Ayam bakar bumbu rempah madu pedas meresap sempurna disajikan dengan sambal terasi.',
            ],
            [
                'category_id' => $categories['makanan-utama']->id,
                'name' => 'Bebek Goreng Crispy Kremes',
                'sku' => 'FOD-003',
                'price' => 48000,
                'cost_price' => 24000,
                'stock' => 50,
                'image_url' => 'https://images.unsplash.com/photo-1518492104633-130d0cc84637?auto=format&fit=crop&w=400&q=80',
                'description' => 'Bebek muda gurih renyah dengan taburan kremes melimpah dan sambal korek hijau.',
            ],
            [
                'category_id' => $categories['makanan-utama']->id,
                'name' => 'Kwetiau Goreng Seafood',
                'sku' => 'FOD-004',
                'price' => 42000,
                'cost_price' => 20000,
                'stock' => 70,
                'image_url' => 'https://images.unsplash.com/photo-1552611052-33e04de081de?auto=format&fit=crop&w=400&q=80',
                'description' => 'Kwetiau kenyal ditumis dengan udang, cumi segar, fish cake, dan tauge renyah.',
            ],
            [
                'category_id' => $categories['makanan-utama']->id,
                'name' => 'Beef Burger Deluxe Qurais Kitchen',
                'sku' => 'FOD-005',
                'price' => 55000,
                'cost_price' => 26000,
                'stock' => 60,
                'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=400&q=80',
                'description' => 'Patty daging sapi murni tebal 150g dengan lelehan keju cheddar, saus bbq, dan caramelized onion.',
            ],

            // Minuman
            [
                'category_id' => $categories['minuman-segar']->id,
                'name' => 'Es Kopi Susu Aren Qurais Kitchen',
                'sku' => 'BEV-001',
                'price' => 22000,
                'cost_price' => 8000,
                'stock' => 150,
                'image_url' => 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?auto=format&fit=crop&w=400&q=80',
                'description' => 'Kopi espresso robusta arabica blend dipadu susu creamy dan gula aren organik murni.',
            ],
            [
                'category_id' => $categories['minuman-segar']->id,
                'name' => 'Matcha Latte Ice Float',
                'sku' => 'BEV-002',
                'price' => 28000,
                'cost_price' => 11000,
                'stock' => 90,
                'image_url' => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?auto=format&fit=crop&w=400&q=80',
                'description' => 'Matcha asli Kyoto dengan susu segar dan topping vanilla ice cream lembut.',
            ],
            [
                'category_id' => $categories['minuman-segar']->id,
                'name' => 'Fresh Lemon Tea Mint',
                'sku' => 'BEV-003',
                'price' => 18000,
                'cost_price' => 5000,
                'stock' => 120,
                'image_url' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=400&q=80',
                'description' => 'Teh hitam premium berpadu perasan lemon asli dan daun mint segar penyejuk dahaga.',
            ],
            [
                'category_id' => $categories['minuman-segar']->id,
                'name' => 'Thai Tea Original Ice',
                'sku' => 'BEV-004',
                'price' => 20000,
                'cost_price' => 7000,
                'stock' => 110,
                'image_url' => 'https://images.unsplash.com/photo-1571328003758-4a3921661729?auto=format&fit=crop&w=400&q=80',
                'description' => 'Thai tea racikan otentik ChaTraMue dengan kental manis dan evaporated milk.',
            ],

            // Snack
            [
                'category_id' => $categories['snack-camilan']->id,
                'name' => 'Truffle French Fries',
                'sku' => 'SNK-001',
                'price' => 25000,
                'cost_price' => 9000,
                'stock' => 80,
                'image_url' => 'https://images.unsplash.com/photo-1576107232684-1279f3908594?auto=format&fit=crop&w=400&q=80',
                'description' => 'Kentang goreng renyah dengan aroma minyak truffle harum dan taburan keju parmesan.',
            ],
            [
                'category_id' => $categories['snack-camilan']->id,
                'name' => 'Dimsum Ayam Udang (4 pcs)',
                'sku' => 'SNK-002',
                'price' => 24000,
                'cost_price' => 10000,
                'stock' => 75,
                'image_url' => 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?auto=format&fit=crop&w=400&q=80',
                'description' => 'Siomay dimsum kukus padat berisi ayam dan udang segar dengan saus chili oil pedas gurih.',
            ],
            [
                'category_id' => $categories['snack-camilan']->id,
                'name' => 'Crispy Chicken Wings BBQ',
                'sku' => 'SNK-003',
                'price' => 32000,
                'cost_price' => 14000,
                'stock' => 65,
                'image_url' => 'https://images.unsplash.com/photo-1567620832903-9fc6debc209f?auto=format&fit=crop&w=400&q=80',
                'description' => 'Sayap ayam crispy berlumur saus smokey BBQ karamel gurih lezat.',
            ],

            // Dessert
            [
                'category_id' => $categories['dessert-manis']->id,
                'name' => 'Choco Lava Cake Ice Cream',
                'sku' => 'DST-001',
                'price' => 28000,
                'cost_price' => 11000,
                'stock' => 50,
                'image_url' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=400&q=80',
                'description' => 'Kue coklat lembut dengan lelehan coklat panas di dalam dipadukan es krim vanilla dingin.',
            ],
            [
                'category_id' => $categories['dessert-manis']->id,
                'name' => 'Pisang Bakar Coklat Keju',
                'sku' => 'DST-002',
                'price' => 20000,
                'cost_price' => 7000,
                'stock' => 70,
                'image_url' => 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af?auto=format&fit=crop&w=400&q=80',
                'description' => 'Pisang raja bakar legit dipadu parutan keju melimpah, coklat mesis dan susu kental manis.',
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $prod) {
            $createdProducts[] = Product::updateOrCreate(['sku' => $prod['sku']], $prod);
        }

        // 7. Initial state: No mockup orders - clean slate for live POS & KDS
    }
}
