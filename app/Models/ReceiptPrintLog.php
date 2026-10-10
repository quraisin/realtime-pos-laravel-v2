<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptPrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_number',
        'print_method',
        'printer_name',
        'order_created_at',
        'printed_at',
        'latency_ms',
        'duration_ms',
        'status',
        'error_message',
        'cashier_id',
    ];

    protected $casts = [
        'order_created_at' => 'datetime',
        'printed_at' => 'datetime',
        'latency_ms' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
