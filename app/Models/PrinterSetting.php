<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_name',
        'store_tagline',
        'store_address',
        'store_phone',
        'paper_size',
        'windows_printer_name',
        'direct_hardware_print',
        'receipt_footer',
        'auto_print_cashier',
        'auto_print_kitchen',
        'cut_paper',
        'open_cash_drawer',
    ];

    protected $casts = [
        'direct_hardware_print' => 'boolean',
        'auto_print_cashier' => 'boolean',
        'auto_print_kitchen' => 'boolean',
        'cut_paper' => 'boolean',
        'open_cash_drawer' => 'boolean',
    ];

    public static function instance(): self
    {
        return static::firstOrCreate([], [
            'store_name' => 'Qurais Kitchen',
            'store_tagline' => 'Cita Rasa Istimewa Setiap Saat',
            'store_address' => 'Jl. Boulevard Raya No. 88, Jakarta Selatan',
            'store_phone' => '0812-3456-7890',
            'paper_size' => '58mm',
            'receipt_footer' => "Terima Kasih Atas Kunjungan Anda!\nBarang yang sudah dibeli tidak dapat ditukar.",
            'auto_print_cashier' => true,
            'auto_print_kitchen' => true,
            'cut_paper' => true,
            'open_cash_drawer' => true,
        ]);
    }
}
