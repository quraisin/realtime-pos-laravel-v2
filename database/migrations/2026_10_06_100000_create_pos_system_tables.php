<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add username column to users if not present
        if (!Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->nullable()->unique()->after('name');
            });
        }

        // Categories Table
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->default('utensils');
            $table->string('color')->default('#ef4444');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Products Table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->integer('stock')->default(100);
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        // Orders Table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('customer_name')->default('Pelanggan Umum');
            $table->string('table_number')->nullable();
            $table->string('order_type')->default('dine_in'); // dine_in, take_away, delivery
            $table->string('payment_method')->default('cash'); // cash, qris, debit_card, credit_card
            $table->string('payment_status')->default('paid'); // paid, pending, refunded, void
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(10.00); // 10%
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('cash_tendered', 12, 2)->default(0);
            $table->decimal('change_due', 12, 2)->default(0);
            $table->string('kitchen_status')->default('pending'); // pending, preparing, ready, completed, cancelled
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('kitchen_started_at')->nullable();
            $table->timestamp('kitchen_ready_at')->nullable();
            $table->timestamp('kitchen_completed_at')->nullable();
            $table->timestamps();
        });

        // Order Items Table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('price', 12, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 12, 2);
            $table->string('notes')->nullable();
            $table->boolean('is_prepared')->default(false);
            $table->timestamps();
        });

        // Printer Settings Table
        Schema::create('printer_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name')->default('BEHEMOTH RESTO & POS');
            $table->string('store_tagline')->default('Cita Rasa Premium Setiap Saat');
            $table->string('store_address')->default('Jl. Boulevard Raya No. 88, Jakarta Selatan');
            $table->string('store_phone')->default('0812-3456-7890');
            $table->string('paper_size')->default('58mm'); // 58mm or 80mm
            $table->text('receipt_footer')->default("Terima Kasih Atas Kunjungan Anda!\nBarang yang sudah dibeli tidak dapat ditukar.");
            $table->boolean('auto_print_cashier')->default(true);
            $table->boolean('auto_print_kitchen')->default(true);
            $table->boolean('cut_paper')->default(true);
            $table->boolean('open_cash_drawer')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printer_settings');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');

        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        }
    }
};
