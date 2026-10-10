<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrinterSetting;
use App\Models\Product;
use App\Services\EscPosReceiptService;
use Tests\TestCase;

class EscPosReceiptServiceTest extends TestCase
{
    private EscPosReceiptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EscPosReceiptService();
    }

    public function test_format_two_columns_aligns_correctly(): void
    {
        $result = $this->service->formatTwoColumns('Subtotal', 'Rp 50.000', 32);
        $this->assertEquals(32, strlen($result));
        $this->assertStringStartsWith('Subtotal', $result);
        $this->assertStringEndsWith('Rp 50.000', $result);
    }

    public function test_center_text_within_width(): void
    {
        $result = $this->service->centerText('BEHEMOTH RESTO', 32);
        $this->assertEquals(32, strlen($result));
        $this->assertStringContainsString('BEHEMOTH RESTO', $result);
    }

    public function test_generate_receipt_plain_text(): void
    {
        $order = new Order([
            'order_number' => 'TRX-20261006-0001',
            'customer_name' => 'Budi Santoso',
            'table_number' => 'Meja 01',
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 50000,
            'tax_rate' => 10.0,
            'tax_amount' => 5000,
            'discount_amount' => 0,
            'total_amount' => 55000,
            'cash_tendered' => 60000,
            'change_due' => 5000,
            'created_at' => now(),
        ]);

        $item = new OrderItem([
            'product_name' => 'Nasi Goreng Wagyu',
            'price' => 50000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);
        $order->setRelation('items', collect([$item]));

        $settings = new PrinterSetting([
            'store_name' => 'BEHEMOTH RESTO',
            'store_address' => 'Jl. Boulevard No. 88',
            'store_phone' => '0812-3456',
            'paper_size' => '58mm',
            'receipt_footer' => 'Terima kasih!',
        ]);

        $plainText = $this->service->generateReceiptPlainText($order, $settings);

        $this->assertStringContainsString('BEHEMOTH RESTO', $plainText);
        $this->assertStringContainsString('TRX-20261006-0001', $plainText);
        $this->assertStringContainsString('Nasi Goreng Wagyu', $plainText);
        $this->assertStringContainsString('55.000', $plainText);
        $this->assertStringContainsString('Kembalian', $plainText);
    }

    public function test_generate_receipt_bytes_contains_esc_pos_commands(): void
    {
        $order = new Order([
            'order_number' => 'TRX-20261006-0002',
            'customer_name' => 'Andi',
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 20000,
            'total_amount' => 22000,
            'cash_tendered' => 50000,
            'change_due' => 28000,
            'created_at' => now(),
        ]);
        $order->setRelation('items', collect([]));

        $settings = new PrinterSetting([
            'store_name' => 'TEST RESTO',
            'store_address' => 'Alamat',
            'store_phone' => '123',
            'paper_size' => '80mm',
            'cut_paper' => true,
            'open_cash_drawer' => true,
        ]);

        $bytes = $this->service->generateReceiptBytes($order, $settings);

        // Contains Init (ESC @)
        $this->assertStringContainsString("\x1B\x40", $bytes);
        // Contains Cut paper (GS V 0 - zero-feed cut)
        $this->assertStringContainsString("\x1D\x56\x00", $bytes);
        // Contains Cash drawer kick (ESC p)
        $this->assertStringContainsString("\x1B\x70\x00\x19\xFA", $bytes);
    }

    public function test_generate_kitchen_ticket_bytes(): void
    {
        $order = new Order([
            'order_number' => 'TRX-20261006-0003',
            'customer_name' => 'Siti',
            'table_number' => 'Meja 04',
            'order_type' => 'dine_in',
            'created_at' => now(),
        ]);

        $item = new OrderItem([
            'product_name' => 'Ayam Bakar Madu',
            'quantity' => 2,
            'notes' => 'Ekstra Sambal',
        ]);
        $order->setRelation('items', collect([$item]));

        $settings = new PrinterSetting([
            'paper_size' => '58mm',
            'cut_paper' => true,
        ]);

        $bytes = $this->service->generateKitchenTicketBytes($order, $settings);

        $this->assertStringContainsString('TIKET DAPUR', $bytes);
        $this->assertStringContainsString('MEJA: Meja 04', $bytes);
        $this->assertStringContainsString('2x Ayam Bakar Madu', $bytes);
        $this->assertStringContainsString('EKSTRA SAMBAL', $bytes);
    }

    public function test_generate_kitchen_ticket_plain_text(): void
    {
        $order = new Order([
            'order_number' => 'TRX-20261006-0004',
            'customer_name' => 'Doni',
            'table_number' => 'Meja 08',
            'order_type' => 'dine_in',
            'created_at' => now(),
        ]);

        $item = new OrderItem([
            'product_name' => 'Bebek Crispy',
            'quantity' => 1,
            'is_prepared' => false,
        ]);
        $order->setRelation('items', collect([$item]));

        $text = $this->service->generateKitchenTicketPlainText($order);

        $this->assertStringContainsString('TIKET DAPUR', $text);
        $this->assertStringContainsString('MEJA: Meja 08', $text);
        $this->assertStringContainsString('1x Bebek Crispy', $text);
    }

    public function test_generate_kitchen_item_ticket_bytes_and_plain_text(): void
    {
        $order = new Order([
            'order_number' => 'TRX-20261006-0005',
            'customer_name' => 'Maya',
            'table_number' => 'Meja 02',
            'order_type' => 'dine_in',
            'created_at' => now(),
        ]);

        $item = new OrderItem([
            'id' => 99,
            'order_id' => 1,
            'product_name' => 'Choco Lava Cake',
            'quantity' => 2,
            'notes' => 'Hangat',
            'is_prepared' => false,
        ]);
        $item->setRelation('order', $order);

        $bytes = $this->service->generateKitchenItemTicketBytes($item);
        $this->assertStringContainsString('TIKET MENU', $bytes);
        $this->assertStringContainsString('2x Choco Lava Cake', $bytes);
        $this->assertStringContainsString('HANGAT', $bytes);

        $text = $this->service->generateKitchenItemTicketPlainText($item);
        $this->assertStringContainsString('TIKET MENU', $text);
        $this->assertStringContainsString('2x Choco Lava Cake', $text);
        $this->assertStringContainsString('CATATAN: Hangat', $text);
    }
}
