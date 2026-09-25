<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('name', 80);
            $table->unsignedTinyInteger('color')->default(0); // index into the chart palette
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->ulid('category_id');
            $table->unsignedBigInteger('amount'); // rial
            $table->date('spent_on'); // tenant-local business day
            $table->string('method', 12);
            $table->string('payee', 120)->nullable();
            $table->string('note', 300)->nullable();
            $table->ulid('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'spent_on']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
            $table->foreign(['category_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('expense_categories');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('branch_id');
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete(); // linked panel account (self clock-in)
            $table->string('name', 120);
            $table->string('phone_e164', 20)->nullable();
            $table->string('position', 60)->nullable();
            $table->string('pay_type', 8)->default('hourly'); // hourly | monthly
            $table->unsignedBigInteger('rate')->default(0); // rial per hour or per month
            $table->date('hired_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'user_id']);
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('employee_id');
            $table->ulid('branch_id');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('note', 120)->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'starts_at']);
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->ulid('employee_id');
            $table->ulid('branch_id');
            $table->ulid('shift_id')->nullable();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->string('source', 8)->default('self'); // self | manager
            $table->string('note', 200)->nullable();
            $table->ulid('edited_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'clock_in_at']);
            $table->index(['tenant_id', 'employee_id', 'clock_out_at']);
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches');
            $table->foreign(['shift_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['attendance_records', 'shifts', 'employees', 'expenses', 'expense_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
