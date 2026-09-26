<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('special_days')->nullable()->after('is_featured'); // ISO weekdays on which it is «پیشنهاد امروز»
        });

        // The café's own diet/allergy tags, next to the platform's fixed list (products store "c:{id}").
        Schema::create('menu_tags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('label', 40);
            $table->timestamps();

            $table->unique(['tenant_id', 'label']);
            $table->unique(['id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_tags');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('special_days'));
    }
};
