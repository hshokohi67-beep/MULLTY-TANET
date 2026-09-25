<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform invoices can now sell things other than the subscription (kind `ad`): those carry no
     * plan/cycle/mode and point at what they pay for through `subject_id`. Fulfilment is done by
     * the module that registered the kind (`Billing\Contracts\InvoiceFulfiller`).
     */
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->ulid('plan_id')->nullable()->change();
            $table->string('cycle', 8)->nullable()->change();
            $table->string('mode', 10)->nullable()->change();
            $table->ulid('subject_id')->nullable()->after('kind');
            $table->index(['kind', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex(['kind', 'subject_id']);
            $table->dropColumn('subject_id');
        });
    }
};
