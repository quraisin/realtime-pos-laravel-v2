<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = App\Models\Order::create([
    'order_number' => 'TRX-TEST-PREVIEW',
    'customer_name' => 'Budi Santoso',
    'table_number' => 'Meja 05',
    'order_type' => 'dine_in',
    'payment_method' => 'cash',
    'payment_status' => 'paid',
    'subtotal' => 100000,
    'tax_rate' => 10,
    'tax_amount' => 10000,
    'discount_amount' => 5000,
    'total_amount' => 105000,
    'cash_tendered' => 150000,
    'change_due' => 45000,
    'notes' => 'Jangan pedas sama sekali',
    'created_at' => now(),
]);
$order->items()->create([
    'product_name' => 'Nasi Goreng Wagyu Spesial Extra Telur',
    'price' => 50000,
    'quantity' => 2,
    'subtotal' => 100000,
    'notes' => 'Telor mata sapi matang',
]);
$order->load(['items', 'cashier']);
$svc = app(App\Services\EscPosReceiptService::class);
echo "=== PREVIEW PLAIN TEXT OUTPUT ===\n";
echo $svc->generateReceiptPlainText($order) . "\n";
echo "=================================\n";

$order->items()->delete();
$order->delete();
