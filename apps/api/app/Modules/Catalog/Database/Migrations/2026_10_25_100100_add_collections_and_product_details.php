<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A time-boxed highlight on the menu («طعم‌های پاییزی», «ویژه‌ی یلدا») with a seasonal theme.
        Schema::create('menu_collections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('title', 80);
            $table->string('subtitle', 160)->nullable();
            $table->string('theme', 20)->nullable();          // nowruz | yalda | autumn | winter | summer | ramadan
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('occasion_key', 60)->nullable();   // the calendar occasion it was made for
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('menu_collection_products', function (Blueprint $table) {
            $table->ulid('tenant_id');
            $table->ulid('menu_collection_id');
            $table->ulid('product_id');
            $table->unsignedSmallInteger('sort')->default(0);

            $table->primary(['menu_collection_id', 'product_id']);
            $table->foreign(['menu_collection_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('menu_collections')->cascadeOnDelete();
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->cascadeOnDelete();
        });

        // Richer product details (from the kaicoffeeco review).
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_en', 160)->nullable()->after('name');
            $table->string('ingredients', 500)->nullable()->after('description');
            $table->json('allergens')->nullable()->after('dietary_tags');
            $table->string('caffeine_source', 12)->nullable()->after('allergens'); // coffee | tea | chocolate | energy
            $table->string('caffeine_dose', 40)->nullable()->after('caffeine_source'); // «۲ شات»
            $table->boolean('is_new')->default(false)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['name_en', 'ingredients', 'allergens', 'caffeine_source', 'caffeine_dose', 'is_new']));
        Schema::dropIfExists('menu_collection_products');
        Schema::dropIfExists('menu_collections');
    }
};
