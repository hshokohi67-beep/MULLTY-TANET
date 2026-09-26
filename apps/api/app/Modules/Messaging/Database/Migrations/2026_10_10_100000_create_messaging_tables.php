<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // The café's own SMS panel: everything it sends to its customers goes through this line.
        Schema::create('sms_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained();
            $table->string('provider', 20);
            $table->text('credentials'); // encrypted JSON
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('key', 32);
            $table->boolean('is_enabled')->default(false);
            $table->string('body', 500);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 80);
            $table->string('body', 600);
            $table->json('audience');
            $table->string('status', 12); // draft | scheduled | sending | done | cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->ulid('cursor')->nullable(); // last customer id sent to
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['status', 'scheduled_at']);
        });

        // Send log (pruned after 180 days).
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('kind', 20);
            $table->ulid('campaign_id')->nullable();
            $table->string('recipient', 20);
            $table->string('body', 700);
            $table->unsignedTinyInteger('parts');
            $table->string('status', 10); // sent | failed | skipped
            $table->string('error', 80)->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('provider_ref', 64)->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index('campaign_id');
            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('sms_campaigns');
        });

        // The old, never-wired «کلید API کاوه‌نگار» setting becomes a real connected account.
        $now = now();
        foreach (DB::table('tenant_settings')->where('key', 'integrations.sms.kavenegar_api_key')->whereNotNull('value')->get() as $row) {
            $key = $row->is_encrypted ? Crypt::decryptString((string) $row->value) : (string) $row->value;
            if ($key !== '' && ! DB::table('sms_accounts')->where('tenant_id', $row->tenant_id)->exists()) {
                DB::table('sms_accounts')->insert([
                    'id' => (string) Str::ulid(), 'tenant_id' => $row->tenant_id, 'provider' => 'kavenegar',
                    'credentials' => Crypt::encryptString((string) json_encode(['api_key' => $key, 'sender' => ''])),
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        DB::table('tenant_settings')->where('key', 'integrations.sms.kavenegar_api_key')->delete();
    }

    public function down(): void
    {
        foreach (['sms_logs', 'sms_campaigns', 'sms_templates', 'sms_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
