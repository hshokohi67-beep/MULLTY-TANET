<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One browser's push subscription: a customer following one order, or a staff member's device.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('audience', 10); // customer | staff
            $table->foreignUlid('user_id')->nullable()->constrained()->cascadeOnDelete(); // staff
            $table->ulid('order_id')->nullable(); // customer: the order being followed
            $table->char('endpoint_hash', 64);
            $table->text('endpoint');  // encrypted
            $table->text('p256dh');    // encrypted
            $table->text('auth');      // encrypted
            $table->text('url')->nullable(); // encrypted: where a tap goes (a tracking link holds its token)
            $table->unsignedTinyInteger('failures')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'endpoint_hash', 'order_id']);
            $table->index(['tenant_id', 'audience']);
            $table->foreign(['order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
