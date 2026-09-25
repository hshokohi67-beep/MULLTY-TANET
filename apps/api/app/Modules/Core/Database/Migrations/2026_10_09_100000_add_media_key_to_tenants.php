<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A random per-tenant folder name for public media, so image URLs no longer carry the tenant
     * id. Files already stored under tenants/{id}/ keep their paths and keep working.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('media_key', 24)->nullable()->unique();
        });
        foreach (DB::table('tenants')->whereNull('media_key')->pluck('id') as $id) {
            DB::table('tenants')->where('id', $id)->update(['media_key' => Str::lower(Str::random(20))]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['media_key']);
            $table->dropColumn('media_key');
        });
    }
};
