<?php

namespace App\Modules\Analytics\Support;

use App\Support\Localization\JalaliDate;
use Carbon\CarbonImmutable;

/** A range of tenant-local business dates (inclusive) and its comparison range. */
final class Period
{
    public const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    public readonly int $days;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $timezone,
    ) {
        $this->days = (int) $from->diffInDays($to) + 1;
    }

    public static function make(string $from, string $to, string $timezone): self
    {
        return new self(CarbonImmutable::parse($from, $timezone)->startOfDay(), CarbonImmutable::parse($to, $timezone)->startOfDay(), $timezone);
    }

    /** The period to compare with: the same number of days just before, or the same dates a year earlier. */
    public function compare(string $mode): ?self
    {
        return match ($mode) {
            'previous' => new self($this->from->subDays($this->days), $this->from->subDay(), $this->timezone),
            'last_year' => new self($this->from->subYear(), $this->to->subYear(), $this->timezone),
            default => null,
        };
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    public function includes(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->from, $this->to);
    }

    /** Jalali "مهر ۱۴۰۵"-style key for month buckets. */
    public static function monthKey(CarbonImmutable $date, string $timezone): string
    {
        $j = JalaliDate::toJalali($date->setTime(12, 0), $timezone);

        return sprintf('%04d-%02d', $j['year'], $j['month']);
    }

    public static function monthLabel(string $key): string
    {
        [$year, $month] = array_map('intval', explode('-', $key));

        return self::MONTHS[$month - 1].' '.strtr((string) $year, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /** Persian-digit Jalali date, e.g. ۱۴۰۵/۰۷/۰۳. */
    public static function jalali(CarbonImmutable $date, string $timezone): string
    {
        return JalaliDate::format($date->setTime(12, 0), 'yyyy/MM/dd', $timezone);
    }
}
