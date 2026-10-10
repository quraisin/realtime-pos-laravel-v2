<?php

use App\Models\PrinterSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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

        // 2. Roles
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->syncPermissions($permissions);

        // 3. Superadmin user: adnan@superadmin.com / Blessings777!
        $superadminUser = User::updateOrCreate(
            ['email' => 'adnan@superadmin.com'],
            [
                'name' => 'Adnan Superadmin',
                'username' => 'adnan@superadmin.com',
                'password' => Hash::make('Blessings777!'),
            ]
        );
        $superadminUser->syncRoles([$superadminRole]);

        // 4. Update cashier name and printer setting
        User::where('username', 'cashier@admin')->update(['name' => 'Qurais Kitchen']);
        PrinterSetting::query()->update([
            'store_name' => 'Qurais Kitchen',
            'store_tagline' => 'Cita Rasa Istimewa Setiap Saat',
        ]);

        // 5. Update any product names
        Product::where('name', 'like', '%Behemoth%')->get()->each(function ($p) {
            $p->name = str_replace('Behemoth', 'Qurais Kitchen', $p->name);
            $p->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse if needed
    }
};
