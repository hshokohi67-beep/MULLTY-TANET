<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Round picture on the category chip in the online menu.
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('temperature');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('image_path'));
    }
};
