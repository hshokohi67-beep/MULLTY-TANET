<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('customer_id');
            $table->string('title', 40);                    // «خانه» (home), «محل کار» (work)
            $table->string('recipient_name', 120)->nullable();
            $table->string('recipient_phone_e164', 16)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('city', 60);
            $table->string('district', 80)->nullable();      // منطقه / محله (district / neighbourhood)
            $table->string('address', 500);
            $table->string('postal_code', 10)->nullable();
            $table->string('building_number', 20)->nullable(); // پلاک (building number)
            $table->string('floor', 10)->nullable();
            $table->string('unit', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('notes', 300)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'tenant_id']);
            $table->index(['customer_id', 'is_default']);
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
