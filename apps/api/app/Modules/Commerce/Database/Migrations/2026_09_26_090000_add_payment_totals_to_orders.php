<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised payment totals, maintained only by the Payments module (SyncOrderPaymentStatus),
 * so order lists can show what is paid/remaining without joining payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_total')->default(0)->after('total');
            $table->unsignedBigInteger('refunded_total')->default(0)->after('paid_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['paid_total', 'refunded_total']);
        });
    }
};
