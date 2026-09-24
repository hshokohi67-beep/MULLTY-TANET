<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('status', 20)->default('trial')->index();
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->string('locale', 10)->default('fa');
            $table->string('currency', 3)->default('IRR');
            $table->string('display_currency_unit', 10)->default('toman');
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('domain')->unique();
            $table->string('type', 20); // subdomain | custom
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('tenant_branding', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#0F766E');
            $table->string('theme', 10)->default('light'); // light | dark
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_branding');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
