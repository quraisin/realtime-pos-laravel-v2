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
        // 1. Add receipt print tracking columns to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('receipt_printed_at')->nullable()->after('notes');
            $table->integer('receipt_print_latency_ms')->nullable()->after('receipt_printed_at')->comment('Latency from order created until receipt printed in milliseconds');
            $table->string('receipt_print_method')->nullable()->after('receipt_print_latency_ms')->comment('auto_hardware, manual_hardware, web_serial, etc');
            $table->integer('receipt_print_count')->default(0)->after('receipt_print_method');
        });

        // 2. Create receipt_print_logs table for comprehensive print auditing
        Schema::create('receipt_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('order_number')->index();
            $table->string('print_method')->default('auto_hardware'); // auto_hardware, manual_hardware, web_serial, browser
            $table->string('printer_name')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->integer('latency_ms')->default(0)->comment('Total latency from order created to print completed in ms');
            $table->integer('duration_ms')->nullable()->comment('Print execution duration in ms');
            $table->string('status')->default('success'); // success, failed
            $table->text('error_message')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_print_logs');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_printed_at',
                'receipt_print_latency_ms',
                'receipt_print_method',
                'receipt_print_count',
            ]);
        });
    }
};
