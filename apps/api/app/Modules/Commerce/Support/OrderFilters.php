<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Enums\PaymentStatus;
use App\Modules\Commerce\Models\Order;
use App\Support\Localization\PersianNumber;
use App\Support\Localization\PersianTextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The staff order list filters, shared by the live board, the history list and its summary.
 * Dates are business dates (tenant-local, Gregorian Y-m-d); the UI converts from Jalali.
 */
final class OrderFilters
{
    /** @return array<string, mixed> */
    public static function validate(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'string', 'max:80'],       // "open", "closed", or comma-separated statuses
            'branch_id' => ['nullable', 'string', 'max:26'],
            'type' => ['nullable', Rule::enum(OrderType::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:60'],
            'completed_within' => ['nullable', 'integer', 'between:1,60'], // minutes; "just finished" notifications
        ]);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<Order>
     */
    public static function apply(array $f): Builder
    {
        $statuses = match (true) {
            empty($f['status']) => null,
            $f['status'] === 'open' => array_map(fn (OrderStatus $s) => $s->value, array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->isOpen()))),
            $f['status'] === 'closed' => [OrderStatus::Completed->value, OrderStatus::Cancelled->value, OrderStatus::Rejected->value],
            default => explode(',', (string) $f['status']),
        };

        $query = Order::query()
            ->when($statuses, fn ($q, $s) => $q->whereIn('status', $s))
            ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($f['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($f['payment_status'] ?? null, fn ($q, $ps) => $q->where('payment_status', $ps))
            ->when($f['date'] ?? null, fn ($q, $date) => $q->where('business_date', $date))
            ->when($f['from'] ?? null, fn ($q, $date) => $q->where('business_date', '>=', $date))
            ->when($f['to'] ?? null, fn ($q, $date) => $q->where('business_date', '<=', $date))
            ->when($f['completed_within'] ?? null, fn ($q, $minutes) => $q->where('completed_at', '>=', Carbon::now()->subMinutes((int) $minutes)));

        $term = trim((string) ($f['q'] ?? ''));
        if ($term !== '') {
            $digits = (string) preg_replace('/\D/', '', PersianNumber::toLatin($term));
            $name = PersianTextNormalizer::forSearch($term);

            $query->where(function (Builder $q) use ($digits, $name): void {
                // "#23" or "23" → that daily number; a longer number → part of the phone.
                if ($digits !== '' && strlen($digits) <= 4) {
                    $q->orWhere('daily_number', (int) $digits);
                }
                if (strlen($digits) >= 4) {
                    $q->orWhere('contact_phone_e164', 'like', '%'.ltrim($digits, '0').'%');
                }
                if ($digits === '' && $name !== '') {
                    $q->orWhere('contact_name', 'like', '%'.addcslashes($name, '%_\\').'%');
                }
            });
        }

        return $query;
    }
}
