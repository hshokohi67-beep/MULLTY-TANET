<?php

namespace App\Modules\Commerce\Enums;

/**
 * Order lifecycle (kitchen/service). Payment has its own state machine ({@see PaymentStatus}).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment'; // Phase 4: waiting for an online payment
    case Placed = 'placed';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'در انتظار پرداخت',
            self::Placed => 'ثبت شد',
            self::Accepted => 'پذیرفته شد',
            self::Preparing => 'در حال آماده‌سازی',
            self::Ready => 'آماده',
            self::OutForDelivery => 'ارسال شد',
            self::Completed => 'تحویل شد',
            self::Rejected => 'رد شد',
            self::Cancelled => 'لغو شد',
        };
    }

    /** @return list<self> */
    public function allowedNext(OrderType $type): array
    {
        return match ($this) {
            self::PendingPayment => [self::Placed, self::Cancelled],
            self::Placed => [self::Accepted, self::Rejected, self::Cancelled],
            self::Accepted => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => $type === OrderType::Delivery ? [self::OutForDelivery, self::Cancelled] : [self::Completed],
            self::OutForDelivery => [self::Completed],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next, OrderType $type): bool
    {
        return in_array($next, $this->allowedNext($type), true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    /** Still in progress from the kitchen/service point of view. */
    public function isOpen(): bool
    {
        return ! $this->isFinal() && $this !== self::PendingPayment;
    }
}
