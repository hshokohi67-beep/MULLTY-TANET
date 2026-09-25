<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // The café's own choice to appear in the marketplace, and platform moderation.
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained();
            $table->boolean('is_listed')->default(false);
            $table->string('headline', 120)->nullable();
            $table->string('about', 600)->nullable();
            $table->json('categories');
            $table->json('amenities');
            $table->unsignedTinyInteger('price_level')->nullable(); // 1 (cheap) … 4 (premium)
            $table->timestamp('listed_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->string('hidden_reason', 200)->nullable();
            $table->timestamp('featured_until')->nullable(); // manual feature by the platform
            $table->timestamps();
        });

        /*
         * The public read model: one row per active branch of an eligible, listed café. Platform-level
         * (not tenant-scoped); written only by ProjectStore with an explicit column list, read only by
         * the public marketplace. tenant_id and popularity are internal and never serialised.
         */
        Schema::create('marketplace_stores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('store_slug', 64);
            $table->string('branch_slug', 64);
            $table->string('name', 120);
            $table->string('branch_name', 120);
            $table->unsignedSmallInteger('branch_count')->default(1);
            $table->string('headline', 120)->nullable();
            $table->string('about', 600)->nullable();
            $table->string('city', 60);
            $table->string('province', 60)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('categories');
            $table->string('category_keys', 200); // "|cafe|bakery|" for LIKE filtering on any database
            $table->json('amenities');
            $table->string('amenity_keys', 300);
            $table->unsignedTinyInteger('price_level')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->json('hours');
            $table->string('timezone', 40);
            $table->json('services');
            $table->json('highlights');
            $table->text('search_text');
            $table->unsignedInteger('popularity')->default(0);
            $table->timestamp('featured_until')->nullable();
            $table->timestamp('listed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_slug', 'branch_slug']);
            $table->index('city');
        });

        // Featured placement is a plan feature, sold as an add-on on every plan.
        foreach (DB::table('plans')->get(['id', 'features']) as $plan) {
            $features = json_decode((string) $plan->features, true) ?: [];
            $features['marketplace_featured'] = false;
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
        DB::table('addons')->insert([
            'id' => (string) Str::ulid(), 'key' => 'marketplace_featured', 'name' => 'ویترین ویژه در بازارگاه',
            'description' => 'نمایش در ردیف «ویژه» و بالای نتایج جست‌وجو', 'monthly_price' => 390_000 * 10,
            'grants' => json_encode(['marketplace_featured' => true]), 'plans' => null, 'is_public' => true, 'sort' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('addons')->where('key', 'marketplace_featured')->delete();
        Schema::dropIfExists('marketplace_stores');
        Schema::dropIfExists('marketplace_listings');
    }
};
