<?php

namespace App\Modules\Billing\Support;

use App\Support\Localization\JalaliDate;
use Illuminate\Support\Facades\DB;

/** Platform-wide, gap-free invoice numbers per Jalali year: "1405-000042". Call inside a transaction. */
final class InvoiceNumbers
{
    public static function next(): string
    {
        $year = JalaliDate::toJalali(now(), 'Asia/Tehran')['year'];

        DB::table('billing_sequences')->insertOrIgnore(['year' => $year, 'last' => 0]);
        $last = (int) DB::table('billing_sequences')->where('year', $year)->lockForUpdate()->value('last') + 1;
        DB::table('billing_sequences')->where('year', $year)->update(['last' => $last]);

        return sprintf('%d-%06d', $year, $last);
    }
}
