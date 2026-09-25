<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The marketplace is now «خوراک‌گردی» (every kind of food store, not only cafés). */
    public function up(): void
    {
        foreach (DB::table('ad_placements')->get(['id', 'name', 'description']) as $p) {
            DB::table('ad_placements')->where('id', $p->id)->update([
                'name' => str_replace('کافه‌گردی', 'خوراک‌گردی', (string) $p->name),
                'description' => str_replace(['کافه‌گردی', 'کارت کافه'], ['خوراک‌گردی', 'کارت فروشگاه'], (string) $p->description),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('ad_placements')->get(['id', 'name', 'description']) as $p) {
            DB::table('ad_placements')->where('id', $p->id)->update([
                'name' => str_replace('خوراک‌گردی', 'کافه‌گردی', (string) $p->name),
                'description' => str_replace(['خوراک‌گردی', 'کارت فروشگاه'], ['کافه‌گردی', 'کارت کافه'], (string) $p->description),
            ]);
        }
    }
};
