<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One landing page per café: its look (design), its words (content) and the section order.
        Schema::create('storefront_landings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained();
            $table->boolean('is_published')->default(false);
            $table->json('design');
            $table->json('content');
            $table->json('sections');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Photos (re-encoded WebP) and the optional hero video of the landing page.
        Schema::create('storefront_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('kind', 16); // hero_photo | hero_video | story_photo | gallery
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('bytes');
            $table->string('caption', 120)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'kind', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_media');
        Schema::dropIfExists('storefront_landings');
    }
};
