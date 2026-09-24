<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\PersianNumber;
use App\Support\Localization\PersianTextNormalizer;
use PHPUnit\Framework\TestCase;

final class PersianTextNormalizerTest extends TestCase
{
    public function test_arabic_and_persian_letter_variants_compare_equal(): void
    {
        $this->assertSame(PersianTextNormalizer::forSearch('کافه'), PersianTextNormalizer::forSearch('كافه'));
        $this->assertSame(PersianTextNormalizer::forSearch('چای'), PersianTextNormalizer::forSearch('چاي'));
        $this->assertSame('قهوه', PersianTextNormalizer::forSearch('قهوة'));
    }

    public function test_digits_zwnj_diacritics_and_whitespace_are_normalized(): void
    {
        $this->assertSame('کیک 2 نفره', PersianTextNormalizer::forSearch('  کیک   ۲ نفره '));
        $this->assertSame('می خواهم', PersianTextNormalizer::forSearch("می\u{200C}خواهم"));
        $this->assertSame('محمد', PersianTextNormalizer::forSearch('مُحَمَّد'));
        $this->assertSame('latte', PersianTextNormalizer::forSearch('LATTE'));
    }

    public function test_empty_and_null_inputs(): void
    {
        $this->assertSame('', PersianTextNormalizer::forSearch(null));
        $this->assertSame('', PersianTextNormalizer::forSearch(''));
    }

    public function test_persian_number_formatting(): void
    {
        $this->assertSame('۱۲۵٬۰۰۰', PersianNumber::format(125000));
        $this->assertSame('09121234567', PersianNumber::toLatin('۰۹۱۲۱۲۳۴۵۶۷'));
    }
}
