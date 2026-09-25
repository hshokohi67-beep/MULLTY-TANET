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
        // Where ads can appear, priced per day by the platform (rial). Platform-level.
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 24)->unique();
            $table->string('name', 80);
            $table->string('description', 200);
            $table->unsignedBigInteger('daily_price');
            $table->unsignedSmallInteger('capacity'); // campaigns running at the same time
            $table->boolean('requires_image');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // A café's campaign: one placement, a date range, target cities and one creative.
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('ref', 24)->unique(); // public, random; never the id
            $table->string('name', 80);
            $table->string('placement', 24);
            $table->string('status', 12); // draft | pending | approved | rejected | paid | suspended | cancelled
            $table->date('start_date');   // the café's local day
            $table->unsignedTinyInteger('days');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->json('cities');       // empty = everywhere
            $table->string('headline', 40);
            $table->string('body', 90)->nullable();
            $table->string('cta', 12);
            $table->string('image_path', 300)->nullable();
            $table->string('image_small_path', 300)->nullable();
            $table->unsignedBigInteger('daily_price'); // quoted when saved
            $table->unsignedBigInteger('amount');      // daily_price × days, before VAT
            $table->ulid('invoice_id')->nullable();
            $table->string('review_note', 200)->nullable();
            $table->string('payment_issue', 20)->nullable(); // paid, but the platform must look at it
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['placement', 'status', 'starts_at', 'ends_at']);
        });

        // Lightweight metrics: counters per campaign per local day (no raw events).
        Schema::create('ad_daily_stats', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('campaign_id');
            $table->date('day');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'day']);
            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('ad_campaigns')->cascadeOnDelete();
        });

        /*
         * The public read model of paid, unsuspended campaigns (platform-level, like
         * marketplace_stores). Written only by ProjectCampaign; tenant_id and campaign_id are internal.
         */
        Schema::create('ad_slots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('campaign_id')->unique();
            $table->string('ref', 24)->unique();
            $table->string('placement', 24);
            $table->string('store_slug', 64);
            $table->string('city_keys', 400); // "|تهران|شیراز|" or "" for everywhere
            $table->string('headline', 40);
            $table->string('body', 90)->nullable();
            $table->string('cta', 12);
            $table->string('image_url', 500)->nullable();
            $table->string('image_small_url', 500)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['placement', 'starts_at', 'ends_at']);
        });

        $now = now();
        foreach ([
            ['home_banner', 'بنر صفحه‌ی اول کافه‌گردی', 'بنر بزرگ و تصویری بالای صفحه‌ی اول، و بالای نتایج شهرهای انتخابی', 180_000, 6, true, 1],
            ['search_top', 'بالای نتایج جست‌وجو', 'کارت کافه با برچسب «تبلیغ» بالای نتایج جست‌وجو و دسته‌ها، فقط وقتی با جست‌وجو جور باشد', 90_000, 12, false, 2],
        ] as [$key, $name, $description, $toman, $capacity, $image, $sort]) {
            DB::table('ad_placements')->insert([
                'id' => (string) Str::ulid(), 'key' => $key, 'name' => $name, 'description' => $description,
                'daily_price' => $toman * 10, 'capacity' => $capacity, 'requires_image' => $image, 'is_active' => true, 'sort' => $sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots');
        Schema::dropIfExists('ad_daily_stats');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_placements');
    }
};
