<?php

namespace App\Modules\Analytics\Http\Requests;

use App\Modules\Analytics\Support\Period;
use App\Modules\Analytics\Support\ReportTables;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantExists;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Report filters: a tenant-local date range (default: the last 30 days, at most 400), branch, comparison. */
final class ReportRequest extends FormRequest
{
    public const MAX_DAYS = 400;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'string', TenantExists::in('branches')],
            'compare' => ['nullable', Rule::in(['previous', 'last_year', 'none'])],
            'sort' => ['nullable', Rule::in(['revenue', 'quantity', 'margin'])],
            'report' => ['nullable', Rule::in(ReportTables::REPORTS)],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');
            if (is_string($from) && is_string($to) && strtotime($from) && strtotime($to) && (strtotime($to) - strtotime($from)) / 86400 >= self::MAX_DAYS) {
                $validator->errors()->add('from', 'بازه‌ی گزارش حداکثر ۴۰۰ روز است.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['from' => 'از تاریخ', 'to' => 'تا تاریخ', 'branch_id' => 'شعبه'];
    }

    public function period(): Period
    {
        $timezone = app(TenantContext::class)->require()->timezone;
        $today = CarbonImmutable::now($timezone)->toDateString();
        $to = $this->string('to', $today)->toString();
        $from = $this->string('from', CarbonImmutable::parse($to)->subDays(29)->toDateString())->toString();

        return Period::make($from, $to, $timezone);
    }

    public function branchId(): ?string
    {
        $id = $this->input('branch_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function compareMode(): string
    {
        return $this->string('compare', 'previous')->toString();
    }
}
