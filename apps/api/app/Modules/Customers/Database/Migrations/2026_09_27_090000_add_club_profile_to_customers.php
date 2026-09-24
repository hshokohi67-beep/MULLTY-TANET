<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Club profile. The birthday is a Jalali month/day: the gift is given on the Jalali date and most
 * people don't want to share their birth year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedTinyInteger('birth_month')->nullable()->after('name');   // Jalali 1..12
            $table->unsignedTinyInteger('birth_day')->nullable()->after('birth_month'); // Jalali 1..31
            $table->boolean('birthday_locked')->default(false)->after('birth_day');   // set once by the customer
            $table->string('referral_code', 12)->nullable()->after('birthday_locked');
            $table->ulid('referred_by_id')->nullable()->after('referral_code');
            $table->timestamp('referred_at')->nullable()->after('referred_by_id');
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_at');
            $table->string('staff_note', 500)->nullable()->after('referral_rewarded_at');
            $table->boolean('marketing_opt_in')->default(true)->after('staff_note');

            $table->unique(['tenant_id', 'referral_code']);
            $table->index(['tenant_id', 'birth_month', 'birth_day']);
            $table->foreign(['referred_by_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id', 'tenant_id']);
            $table->dropUnique(['tenant_id', 'referral_code']);
            $table->dropIndex(['tenant_id', 'birth_month', 'birth_day']);
            $table->dropColumn(['birth_month', 'birth_day', 'birthday_locked', 'referral_code', 'referred_by_id', 'referred_at', 'referral_rewarded_at', 'staff_note', 'marketing_opt_in']);
        });
    }
};
