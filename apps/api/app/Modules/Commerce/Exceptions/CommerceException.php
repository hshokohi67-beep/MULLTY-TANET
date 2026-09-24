<?php

namespace App\Modules\Commerce\Exceptions;

use App\Support\Http\DomainException;
use App\Support\Localization\PersianNumber;

/**
 * Business-rule failures in ordering, with messages written for the customer or staff member.
 */
final class CommerceException extends DomainException
{
    public static function qrInvalid(): self
    {
        return new self('این کد QR معتبر نیست یا منقضی شده است. لطفاً از کارکنان کمک بخواهید.', 'qr_invalid', 404);
    }

    public static function sessionInvalid(): self
    {
        return new self('نشست این میز بسته شده است. لطفاً دوباره کد QR روی میز را اسکن کنید.', 'session_invalid', 410);
    }

    public static function requestCooldown(int $seconds): self
    {
        return new self(sprintf('درخواست شما ثبت شده است. برای درخواست دوباره %s ثانیه صبر کنید.', PersianNumber::toPersian((string) $seconds)), 'request_cooldown', 429);
    }

    public static function cartInvalid(): self
    {
        return new self('سبد خرید پیدا نشد یا منقضی شده است. لطفاً دوباره آیتم‌ها را اضافه کنید.', 'cart_invalid', 404);
    }

    public static function cartEmpty(): self
    {
        return new self('سبد خرید خالی است.', 'cart_empty', 422);
    }

    public static function cartFull(): self
    {
        return new self('تعداد آیتم‌های سبد بیش از حد مجاز است.', 'cart_full', 422);
    }

    public static function productUnavailable(string $name): self
    {
        return new self(sprintf('«%s» در حال حاضر موجود نیست.', $name), 'product_unavailable', 422);
    }

    public static function modifierInvalid(string $productName): self
    {
        return new self(sprintf('گزینه‌های انتخاب‌شده برای «%s» معتبر نیست.', $productName), 'modifier_invalid', 422);
    }

    public static function modifierSelection(string $productName, string $groupName, int $min, int $max): self
    {
        $rule = match (true) {
            $max === 0 => sprintf('دست‌کم %s مورد', PersianNumber::toPersian((string) $min)),
            $min === $max => sprintf('دقیقاً %s مورد', PersianNumber::toPersian((string) $min)),
            default => sprintf('بین %s تا %s مورد', PersianNumber::toPersian((string) $min), PersianNumber::toPersian((string) $max)),
        };

        return new self(sprintf('برای «%s»، از «%s» %s انتخاب کنید.', $productName, $groupName, $rule), 'modifier_selection', 422);
    }

    public static function branchClosed(?string $nextOpening): self
    {
        return new self(
            $nextOpening ? sprintf('شعبه الان بسته است و از %s دوباره باز می‌شود. می‌توانید برای آن زمان پیش‌سفارش بدهید.', $nextOpening) : 'شعبه الان بسته است.',
            'branch_closed',
            422,
        );
    }

    public static function scheduleTooSoon(string $earliest): self
    {
        return new self(sprintf('زودترین زمان قابل انتخاب برای پیش‌سفارش %s است.', $earliest), 'schedule_too_soon', 422);
    }

    public static function scheduleTooFar(): self
    {
        return new self('این زمان از بازه‌ی پیش‌سفارش کافه دورتر است. روز نزدیک‌تری انتخاب کنید.', 'schedule_too_far', 422);
    }

    public static function slotFull(): self
    {
        return new self('ظرفیت این بازه‌ی زمانی پر شده است. بازه‌ی دیگری انتخاب کنید.', 'slot_full', 422);
    }

    public static function scheduleInvalid(): self
    {
        return new self('زمان تحویل انتخاب‌شده در ساعات کاری شعبه نیست. زمان دیگری انتخاب کنید.', 'schedule_invalid', 422);
    }

    public static function preorderDisabled(): self
    {
        return new self('این کسب‌وکار در ساعات تعطیلی سفارش نمی‌پذیرد.', 'preorder_disabled', 422);
    }

    public static function addressRequired(): self
    {
        return new self('برای ارسال، یک آدرس انتخاب کنید.', 'address_required', 422);
    }

    public static function addressNeedsLocation(): self
    {
        return new self('موقعیت این آدرس روی نقشه مشخص نشده است. لطفاً محل را روی نقشه تأیید کنید.', 'address_location_required', 422);
    }

    public static function outOfDeliveryArea(): self
    {
        return new self('متأسفانه این آدرس خارج از محدوده‌ی ارسال این شعبه است.', 'out_of_delivery_area', 422);
    }

    public static function deliveryNotConfigured(): self
    {
        return new self('این شعبه فعلاً ارسال با پیک ندارد.', 'delivery_unavailable', 422);
    }

    public static function belowMinimumOrder(string $minimum): self
    {
        return new self(sprintf('حداقل مبلغ سفارش برای این آدرس %s است.', $minimum), 'below_minimum_order', 422);
    }

    public static function onlinePaymentUnavailable(): self
    {
        return new self('پرداخت اینترنتی برای این کافه فعال نیست؛ لطفاً پرداخت در محل را انتخاب کنید.', 'online_payment_unavailable', 422);
    }

    public static function onlineBelowMinimum(string $minimum): self
    {
        return new self(sprintf('حداقل مبلغ پرداخت اینترنتی %s است.', $minimum), 'online_payment_below_minimum', 422);
    }

    public static function loginRequired(): self
    {
        return new self('برای این نوع سفارش ابتدا با شماره موبایل وارد شوید.', 'login_required', 401);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(sprintf('وضعیت سفارش از «%s» به «%s» قابل تغییر نیست.', $from, $to), 'invalid_transition', 422);
    }

    public static function idempotencyConflict(): self
    {
        return new self('این درخواست قبلاً برای سفارش دیگری استفاده شده است. صفحه را دوباره بارگذاری کنید.', 'idempotency_conflict', 409);
    }

    public static function idempotencyKeyRequired(): self
    {
        return new self('درخواست ثبت سفارش ناقص است. صفحه را دوباره بارگذاری کنید.', 'idempotency_key_required', 400);
    }
}
