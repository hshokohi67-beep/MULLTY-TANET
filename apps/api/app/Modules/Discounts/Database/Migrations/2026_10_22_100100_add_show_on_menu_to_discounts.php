<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            // A menu offer: shown on the cards and applied per item, on top of the order's one discount.
            $table->boolean('show_on_menu')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', fn (Blueprint $table) => $table->dropColumn('show_on_menu'));
    }
};
