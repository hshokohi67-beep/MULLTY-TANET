<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Platform catalogue (not tenant-owned): prices in rial, feature values per plan.
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('tagline', 200)->nullable();
            $table->unsignedBigInteger('monthly_price');
            $table->unsignedBigInteger('yearly_price');
            $table->json('features'); // {feature: bool|int|null}; null limit = unlimited
            $table->boolean('is_public')->default(true);
            $table->boolean('is_trial_plan')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('description', 200)->nullable();
            $table->unsignedBigInteger('monthly_price');
            $table->json('grants'); // {feature: true | +int}
            $table->json('plans')->nullable(); // plan keys it can be added to (null = all)
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained();
            $table->foreignUlid('plan_id')->constrained('plans');
            $table->string('cycle', 8)->default('monthly'); // monthly | yearly
            $table->string('status', 12);                    // trialing | active | cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->foreignUlid('scheduled_plan_id')->nullable()->constrained('plans');
            $table->string('scheduled_cycle', 8)->nullable();
            $table->json('scheduled_addons')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->string('reminder_stage', 12)->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('subscription_id');
            $table->foreignUlid('addon_id')->constrained('addons');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['subscription_id', 'addon_id']);
            $table->foreign(['subscription_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('subscriptions')->cascadeOnDelete();
        });

        // Platform grants: a feature switched on or a limit raised for one tenant, optionally until a date.
        Schema::create('entitlement_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('feature', 40);
            $table->json('value'); // bool | int | null (unlimited)
            $table->string('reason', 200);
            $table->timestamp('expires_at')->nullable();
            $table->ulid('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('number', 20)->unique(); // e.g. 1405-000042
            $table->string('kind', 12);             // checkout | renewal
            $table->string('status', 8);            // open | paid | void
            $table->foreignUlid('plan_id')->constrained('plans');
            $table->string('cycle', 8);
            $table->json('addons');                 // [{addon_id, quantity}]
            $table->string('mode', 10);             // pay_now | renew
            $table->json('lines');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('credit')->default(0);
            $table->unsignedSmallInteger('vat_rate');   // percent
            $table->unsignedBigInteger('vat');
            $table->unsignedBigInteger('total');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_via', 20)->nullable(); // gateway | transfer
            $table->string('reference', 100)->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('invoice_id');
            $table->string('gateway', 20);
            $table->string('status', 10); // pending | paid | failed
            $table->unsignedBigInteger('amount');
            $table->string('authority', 64)->nullable();
            $table->string('ref_id', 64)->nullable();
            $table->string('card_pan', 32)->nullable();
            $table->string('failure_code', 20)->nullable();
            $table->json('log')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'authority']);
            $table->foreign(['invoice_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('billing_invoices')->cascadeOnDelete();
        });

        Schema::create('billing_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary(); // Jalali year
            $table->unsignedInteger('last')->default(0);
        });

        $now = now();
        $toman = fn (int $t) => $t * 10;
        $plans = [
            ['starter', 'پایه', 'برای یک کافه‌ی کوچک: منو، سفارش، QR و پیشخوان', 490_000, 1,
                ['online_payments' => false, 'loyalty' => false, 'inventory' => false, 'operations' => false, 'reports' => false, 'stories' => false, 'custom_domain' => false, 'branches' => 1, 'staff' => 5, 'products' => 150, 'monthly_orders' => 1500], false],
            ['pro', 'حرفه‌ای', 'همه‌ی ابزارهای رشد: پرداخت آنلاین، باشگاه، انبار، گزارش و کارکنان', 1_190_000, 2,
                ['online_payments' => true, 'loyalty' => true, 'inventory' => true, 'operations' => true, 'reports' => true, 'stories' => true, 'custom_domain' => false, 'branches' => 2, 'staff' => 15, 'products' => null, 'monthly_orders' => 6000], true],
            ['chain', 'زنجیره‌ای', 'برای چند شعبه با دامنه‌ی اختصاصی و بدون سقف سفارش', 2_900_000, 3,
                ['online_payments' => true, 'loyalty' => true, 'inventory' => true, 'operations' => true, 'reports' => true, 'stories' => true, 'custom_domain' => true, 'branches' => 10, 'staff' => null, 'products' => null, 'monthly_orders' => null], false],
        ];
        $ids = [];
        foreach ($plans as [$key, $name, $tagline, $monthly, $sort, $features, $trial]) {
            $ids[$key] = (string) Str::ulid();
            DB::table('plans')->insert([
                'id' => $ids[$key], 'key' => $key, 'name' => $name, 'tagline' => $tagline,
                'monthly_price' => $toman($monthly), 'yearly_price' => $toman($monthly * 10),
                'features' => json_encode($features), 'is_public' => true, 'is_trial_plan' => $trial, 'sort' => $sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach ([
            ['extra_branch', 'شعبه‌ی اضافه', 'یک شعبه‌ی بیشتر از سقف پلن', 350_000, ['branches' => 1], null, 1],
            ['extra_staff', '۵ عضو تیم اضافه', 'پنج حساب کاربری بیشتر برای کارکنان', 150_000, ['staff' => 5], null, 2],
            ['custom_domain', 'دامنه‌ی اختصاصی', 'منوی آنلاین روی دامنه‌ی خودتان', 200_000, ['custom_domain' => true], ['pro'], 3],
        ] as [$key, $name, $description, $monthly, $grants, $forPlans, $sort]) {
            DB::table('addons')->insert([
                'id' => (string) Str::ulid(), 'key' => $key, 'name' => $name, 'description' => $description,
                'monthly_price' => $toman($monthly), 'grants' => json_encode($grants), 'plans' => $forPlans === null ? null : json_encode($forPlans),
                'is_public' => true, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Existing cafés start the same 14-day Pro trial a new café gets.
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('subscriptions')->insert([
                'id' => (string) Str::ulid(), 'tenant_id' => $tenantId, 'plan_id' => $ids['pro'], 'cycle' => 'monthly', 'status' => 'trialing',
                'trial_ends_at' => $now->copy()->addDays(14), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['billing_sequences', 'billing_payments', 'billing_invoices', 'entitlement_overrides', 'subscription_addons', 'subscriptions', 'addons', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
