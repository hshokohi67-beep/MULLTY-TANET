<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One saved dashboard per user per tenant: an ordered list of {key, size}.
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->foreignUlid('user_id')->constrained();
            $table->json('widgets');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('shift_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id')->nullable();
            $table->foreignUlid('author_id')->constrained('users');
            $table->string('body', 500);
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_notes');
        Schema::dropIfExists('dashboard_layouts');
    }
};
