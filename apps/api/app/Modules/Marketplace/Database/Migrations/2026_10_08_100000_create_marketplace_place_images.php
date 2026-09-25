<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photos the platform designs for city tiles in «خوراک‌گردی» (platform-level).
        Schema::create('marketplace_place_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('city', 60)->unique();
            $table->string('image_path', 300);
            $table->string('image_small_path', 300);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_place_images');
    }
};
