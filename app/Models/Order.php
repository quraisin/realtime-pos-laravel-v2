<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_name',
        'table_number',
        'order_type',
        'payment_method',
        'payment_status',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'cash_tendered',
        'change_due',
        'kitchen_status',
        'cashier_id',
        'notes',
        'kitchen_started_at',
        'kitchen_ready_at',
        'kitchen_completed_at',
        'receipt_printed_at',
        'receipt_print_latency_ms',
        'receipt_print_method',
        'receipt_print_count',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'discount_amount' => 'float',
        'total_amount' => 'float',
        'cash_tendered' => 'float',
        'change_due' => 'float',
        'kitchen_started_at' => 'datetime',
        'kitchen_ready_at' => 'datetime',
        'kitchen_completed_at' => 'datetime',
        'receipt_printed_at' => 'datetime',
        'receipt_print_latency_ms' => 'integer',
        'receipt_print_count' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(ReceiptPrintLog::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Generate unique sequential order number for today
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'TRX-' . date('Ymd') . '-';
        $latest = static::where('order_number', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($latest) {
            $num = (int) substr($latest->order_number, strlen($prefix));
            $next = str_pad((string)($num + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $next = '0001';
        }

        return $prefix . $next;
    }
}
