<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_stores', function (Blueprint $table) {
            $table->string('district', 60)->nullable()->after('city');
            $table->json('offers')->nullable();                 // public labels of current automatic discounts
            $table->boolean('has_offer')->default(false);
            $table->string('dietary_keys', 200)->default('||'); // "|vegan|gluten_free|" from active products
            $table->boolean('closes_late')->default(false);
            $table->boolean('free_delivery')->default(false);
            $table->index(['city', 'district']);
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_stores', function (Blueprint $table) {
            $table->dropIndex(['city', 'district']);
            $table->dropColumn(['district', 'offers', 'has_offer', 'dietary_keys', 'closes_late', 'free_delivery']);
        });
    }
};
