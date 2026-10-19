<?php

use App\Modules\Occasions\Support\OccasionSeed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The platform's calendar of occasions (not tenant data; edited by platform admins).
        Schema::create('occasions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 60)->unique();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('kind', 20);
            $table->string('tone', 12)->default('festive');
            $table->string('calendar', 12);               // jalali | hijri | gregorian
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('day')->nullable();
            $table->string('rule', 30)->nullable();        // computed dates, e.g. black_friday
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->unsignedSmallInteger('lead_days')->default(14);
            $table->boolean('is_holiday')->default(false);
            $table->json('ideas')->nullable();             // short menu / campaign ideas
            $table->string('campaign_text', 300)->nullable();
            $table->string('theme', 20)->nullable();       // seasonal decoration key
            $table->boolean('is_active')->default(true);
            $table->boolean('is_one_off')->default(false); // a single date added by hand
            $table->timestamps();
        });

        // Each occurrence as Gregorian days; Hijri ones stay approximate until confirmed.
        Schema::create('occasion_dates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('occasion_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');          // in the occasion's own calendar
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('confirmed')->default(false);
            $table->timestamps();

            $table->unique(['occasion_id', 'year']);
            $table->index('starts_on');
        });

        // A café's answer to an occurrence, and whether it was told about it.
        Schema::create('tenant_occasions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->foreignUlid('occasion_date_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->nullable();     // planned | dismissed | done
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'occasion_date_id']);
        });

        OccasionSeed::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_occasions');
        Schema::dropIfExists('occasion_dates');
        Schema::dropIfExists('occasions');
    }
};
