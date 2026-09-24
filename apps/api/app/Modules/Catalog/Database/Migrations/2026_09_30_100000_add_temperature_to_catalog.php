<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menu mood (hot/cold glow on the storefront). Products inherit their category's unless set.
        Schema::table('categories', function (Blueprint $table) {
            $table->string('temperature', 8)->nullable()->after('is_active');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('temperature', 8)->nullable()->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('temperature'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('temperature'));
    }
};
