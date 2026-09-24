<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\InvalidPhoneNumberException;
use App\Support\Localization\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function equivalentForms(): array
    {
        return [
            'local' => ['09121234567'],
            'persian digits' => ['۰۹۱۲۱۲۳۴۵۶۷'],
            'arabic digits' => ['٠٩١٢١٢٣٤٥٦٧'],
            'e164' => ['+989121234567'],
            'double zero' => ['00989121234567'],
            'country code without plus' => ['989121234567'],
            'without leading zero' => ['9121234567'],
            'spaces and dashes' => [' 0912-123 4567 '],
            'parentheses' => ['(0912) 123-4567'],
            'persian with plus' => ['+۹۸۹۱۲۱۲۳۴۵۶۷'],
        ];
    }

    #[DataProvider('equivalentForms')]
    public function test_all_common_forms_normalize_to_one_canonical_number(string $input): void
    {
        $this->assertSame('+989121234567', PhoneNormalizer::normalize($input));
    }

    /** @return array<string, array{?string}> */
    public static function invalidForms(): array
    {
        return [
            'landline' => ['02188776655'],
            'too short' => ['0912123456'],
            'too long' => ['091212345678'],
            'letters' => ['0912abc4567'],
            'empty' => [''],
            'null' => [null],
            'foreign number' => ['+14155552671'],
        ];
    }

    #[DataProvider('invalidForms')]
    public function test_invalid_numbers_are_rejected(?string $input): void
    {
        $this->assertNull(PhoneNormalizer::tryNormalize($input));
    }

    public function test_normalize_throws_for_invalid_input(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        PhoneNormalizer::normalize('123');
    }

    public function test_local_form_for_display(): void
    {
        $this->assertSame('09121234567', PhoneNormalizer::toLocal('+989121234567'));
    }
}
