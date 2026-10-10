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
        Schema::table('printer_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('printer_settings', 'windows_printer_name')) {
                $table->string('windows_printer_name')->default('Putian POS Thermal 2')->after('paper_size');
            }
            if (!Schema::hasColumn('printer_settings', 'direct_hardware_print')) {
                $table->boolean('direct_hardware_print')->default(true)->after('windows_printer_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('printer_settings', function (Blueprint $table) {
            if (Schema::hasColumn('printer_settings', 'direct_hardware_print')) {
                $table->dropColumn('direct_hardware_print');
            }
            if (Schema::hasColumn('printer_settings', 'windows_printer_name')) {
                $table->dropColumn('windows_printer_name');
            }
        });
    }
};
