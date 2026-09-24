<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Customers\Actions\UpdateCustomerProfile;
use App\Modules\Customers\Http\Requests\CustomerProfileRequest;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Loyalty\Actions\PostPointsTransaction;
use App\Modules\Loyalty\Actions\PostWalletTransaction;
use App\Modules\Loyalty\Enums\PointsTransactionType;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Http\Requests\AdjustmentRequest;
use App\Modules\Loyalty\Http\Resources\LedgerResource;
use App\Modules\Loyalty\Http\Resources\StaffCustomerResource;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\Wallet;
use App\Modules\Loyalty\Models\WalletTransaction;
use App\Modules\Loyalty\Support\ClubSummary;
use App\Modules\Loyalty\Support\CustomerDirectory;
use App\Support\Audit\AuditLogger;
use App\Support\Localization\JalaliDate;
use App\Support\Localization\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StaffCustomerController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return StaffCustomerResource::collection(
            CustomerDirectory::query($this->filters($request))
                ->orderByDesc('customers.created_at')->orderByDesc('customers.id')
                ->cursorPaginate(50),
        );
    }

    public function show(Customer $customer): JsonResponse
    {
        $row = CustomerDirectory::query()->where('customers.id', $customer->id)->firstOrFail();

        return response()->json(['data' => [
            ...(new StaffCustomerResource($row))->resolve(),
            'staff_note' => $customer->staff_note,
            'birthday_locked' => $customer->birthday_locked,
            'marketing_opt_in' => $customer->marketing_opt_in,
            'referred_by' => $customer->referrer ? ['id' => $customer->referrer->id, 'name' => $customer->referrer->name] : null,
            'club' => ClubSummary::for($customer),
            'recent_orders' => OrderResource::collection(Order::query()->with('branch')->where('customer_id', $customer->id)->latest('placed_at')->limit(10)->get()),
        ]]);
    }

    public function update(CustomerProfileRequest $request, Customer $customer, UpdateCustomerProfile $update, AuditLogger $audit): JsonResponse
    {
        $before = $customer->only(['name', 'birth_month', 'birth_day', 'staff_note', 'marketing_opt_in']);
        $update->handle($customer, $request->profile(), byStaff: true);
        $audit->record('customer.updated', $customer, ['before' => $before, 'after' => $customer->only(array_keys($before))]);

        return response()->json(['message' => __('messages.saved')]);
    }

    public function walletTransactions(Customer $customer): AnonymousResourceCollection
    {
        $wallet = Wallet::query()->where('customer_id', $customer->id)->value('id');

        return LedgerResource::collection(
            WalletTransaction::query()->where('wallet_id', $wallet)->latest('created_at')->latest('id')->cursorPaginate(50),
        );
    }

    public function pointsTransactions(Customer $customer): AnonymousResourceCollection
    {
        $account = LoyaltyAccount::query()->where('customer_id', $customer->id)->value('id');

        return LedgerResource::collection(
            LoyaltyTransaction::query()->where('account_id', $account)->latest('created_at')->latest('id')->cursorPaginate(50),
        );
    }

    public function adjustWallet(AdjustmentRequest $request, Customer $customer, PostWalletTransaction $post, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $amount = (int) $request->validated('amount');

        $transaction = $post->handle($customer, WalletTransactionType::Adjustment, $amount, 'adjust:'.$request->validated('idempotency_key'), (string) $request->validated('reason'), actorType: 'user', actorId: $user->id);

        if ($transaction->wasRecentlyCreated) {
            $audit->record('wallet.adjusted', $customer, ['amount' => $amount, 'reason' => $request->validated('reason'), 'balance_after' => $transaction->balance_after]);
        }

        return (new LedgerResource($transaction))->additional(['message' => __('messages.wallet_adjusted')])->response()->setStatusCode($transaction->wasRecentlyCreated ? 201 : 200);
    }

    public function adjustPoints(AdjustmentRequest $request, Customer $customer, PostPointsTransaction $post, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $points = (int) $request->validated('amount');

        $transaction = $post->handle($customer, PointsTransactionType::Adjustment, $points, 'adjust:'.$request->validated('idempotency_key'), (string) $request->validated('reason'), actorType: 'user', actorId: $user->id);

        if ($transaction->wasRecentlyCreated) {
            $audit->record('points.adjusted', $customer, ['points' => $points, 'reason' => $request->validated('reason'), 'balance_after' => $transaction->balance_after]);
        }

        return (new LedgerResource($transaction))->additional(['message' => __('messages.points_adjusted')])->response()->setStatusCode($transaction->wasRecentlyCreated ? 201 : 200);
    }

    /** UTF-8 CSV with a BOM (so Excel shows Persian correctly), Persian headers, Jalali dates, toman. */
    public function export(Request $request, AuditLogger $audit, TenantContext $context): StreamedResponse
    {
        $filters = $this->filters($request);
        $tenant = $context->require();
        $timezone = $tenant->timezone;
        $audit->record('customers.exported', null, ['filters' => $filters]);

        // The body is streamed after the middleware has finished (and cleared the tenant context),
        // so the callback re-enters the tenant explicitly.
        return response()->streamDownload(fn () => $context->runAs($tenant, function () use ($filters, $timezone): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['نام', 'موبایل', 'تولد (ماه/روز)', 'سطح', 'کیف پول (تومان)', 'امتیاز', 'مجموع خرید (تومان)', 'تعداد سفارش', 'آخرین سفارش', 'تاریخ عضویت'], escape: '');

            CustomerDirectory::query($filters)->orderBy('customers.created_at')->orderBy('customers.id')
                ->chunk(500, function ($rows) use ($out, $timezone): void {
                    foreach ($rows as $row) {
                        $a = $row->getAttributes();
                        fputcsv($out, array_map($this->cell(...), [
                            $row->name ?? '',
                            PhoneNormalizer::toLocal($row->phone_e164),
                            $row->hasBirthday() ? sprintf('%02d/%02d', $row->birth_month, $row->birth_day) : '',
                            (string) ($a['tier_name'] ?? ''),
                            (string) intdiv((int) ($a['wallet_balance'] ?? 0), 10),
                            (string) (int) ($a['points'] ?? 0),
                            (string) intdiv((int) ($a['lifetime_spend'] ?? 0), 10),
                            (string) (int) ($a['orders_count'] ?? 0),
                            isset($a['last_order_at']) ? JalaliDate::format(Carbon::parse((string) $a['last_order_at'], 'UTC'), 'yyyy/MM/dd', $timezone, persianDigits: false) : '',
                            JalaliDate::format($row->created_at, 'yyyy/MM/dd', $timezone, persianDigits: false),
                        ]), escape: '');
                    }
                });

            fclose($out);
        }), 'customers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Spreadsheet formula injection guard. */
    private function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** @return array{q?: ?string, tier_id?: ?string, birth_month?: ?int} */
    private function filters(Request $request): array
    {
        /** @var array{q?: ?string, tier_id?: ?string, birth_month?: ?int} */
        return $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'tier_id' => ['nullable', 'string', 'max:26'],
            'birth_month' => ['nullable', 'integer', 'between:1,12'],
        ]);
    }
}
