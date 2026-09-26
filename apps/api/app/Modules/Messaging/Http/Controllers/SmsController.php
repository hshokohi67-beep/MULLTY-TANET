<?php

namespace App\Modules\Messaging\Http\Controllers;

use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Modules\Messaging\Actions\ManageSmsAccount;
use App\Modules\Messaging\Actions\ManageSmsCampaigns;
use App\Modules\Messaging\Actions\RunSmsCampaigns;
use App\Modules\Messaging\Exceptions\MessagingException;
use App\Modules\Messaging\Http\Requests\SmsCampaignRequest;
use App\Modules\Messaging\Models\SmsAccount;
use App\Modules\Messaging\Models\SmsCampaign;
use App\Modules\Messaging\Models\SmsLog;
use App\Modules\Messaging\Models\SmsTemplate;
use App\Modules\Messaging\Support\Audience;
use App\Modules\Messaging\Support\SmsText;
use App\Modules\Messaging\Support\TemplateCatalog;
use App\Support\Localization\PhoneNormalizer;
use App\Support\Sms\SmsDrivers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The café's SMS centre (`sms.manage`): its own panel, automatic messages, campaigns and the log. */
final class SmsController
{
    public function show(): JsonResponse
    {
        $account = SmsAccount::query()->first();
        $templates = SmsTemplate::query()->get()->keyBy('key');
        $since = now()->subDays(30);

        return response()->json(['data' => [
            'account' => $account ? $this->account($account) : null,
            'drivers' => SmsDrivers::catalog(),
            'templates' => collect(TemplateCatalog::TEMPLATES)->map(function (array $t, string $key) use ($templates): array {
                $saved = $templates->get($key);

                return [
                    'key' => $key, 'label' => $t['label'], 'hint' => $t['hint'], 'default' => $t['body'],
                    'placeholders' => array_map(fn (string $p) => ['key' => $p, 'label' => TemplateCatalog::PLACEHOLDER_LABELS[$p] ?? $p], $t['placeholders']),
                    'enabled' => (bool) $saved?->is_enabled, 'body' => $saved !== null ? $saved->body : $t['body'],
                ];
            })->values(),
            'stats' => [
                'sent' => SmsLog::query()->where('created_at', '>=', $since)->where('status', 'sent')->count(),
                'failed' => SmsLog::query()->where('created_at', '>=', $since)->where('status', 'failed')->count(),
                'skipped' => SmsLog::query()->where('created_at', '>=', $since)->where('status', 'skipped')->count(),
                'parts' => (int) SmsLog::query()->where('created_at', '>=', $since)->where('status', 'sent')->sum('parts'),
                'opted_in' => Audience::count([]),
            ],
            'campaigns' => SmsCampaign::query()->orderByDesc('created_at')->limit(50)->get()->map(fn (SmsCampaign $c) => $this->campaign($c))->values(),
            'tiers' => LoyaltyTier::query()->orderBy('min_spend')->get(['id', 'name'])->map(fn (LoyaltyTier $t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'rules' => ['quiet_from' => RunSmsCampaigns::QUIET_FROM, 'quiet_to' => RunSmsCampaigns::QUIET_TO, 'daily_cap' => RunSmsCampaigns::DAILY_CAP, 'footer' => trim(SmsText::OPT_OUT_FOOTER)],
        ]]);
    }

    public function saveAccount(Request $request, ManageSmsAccount $manage): JsonResponse
    {
        $v = $request->validate([
            'provider' => ['required', Rule::in(array_keys(SmsDrivers::DRIVERS))],
            'fields' => ['required', 'array'],
            'fields.*' => ['nullable', 'string', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ]);
        $fields = array_map('strval', array_filter((array) $v['fields'], fn ($x) => $x !== null));

        return response()->json(['data' => $this->account($manage->save((string) $v['provider'], $fields, (bool) $v['is_active']))]);
    }

    public function test(Request $request, ManageSmsAccount $manage): JsonResponse
    {
        $phone = (string) ($request->validate(['phone' => ['nullable', 'string', 'max:20']])['phone'] ?? '');
        $e164 = $phone !== '' ? PhoneNormalizer::tryNormalize($phone) : $request->user()?->getAttribute('phone_e164');
        if (! is_string($e164) || $e164 === '') {
            throw MessagingException::missingField('شماره موبایل');
        }
        $ok = $manage->test($e164);

        return response()->json(['data' => ['sent' => $ok, 'account' => $this->account(SmsAccount::query()->firstOrFail())]]);
    }

    public function saveTemplates(Request $request, ManageSmsAccount $manage): JsonResponse
    {
        $v = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.enabled' => ['required', 'boolean'],
            'templates.*.body' => ['required', 'string', 'max:500'],
        ]);
        $templates = [];
        foreach ((array) $v['templates'] as $key => $t) {
            $templates[(string) $key] = ['enabled' => (bool) $t['enabled'], 'body' => (string) $t['body']];
        }
        $manage->saveTemplates($templates);

        return $this->show();
    }

    public function logs(Request $request): JsonResponse
    {
        $v = $request->validate(['kind' => ['nullable', 'string', 'max:20'], 'status' => ['nullable', Rule::in(['sent', 'failed', 'skipped'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $page = SmsLog::query()
            ->when($v['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (SmsLog $l) => [
                'id' => $l->id, 'kind' => $l->kind, 'recipient' => $this->mask($l->recipient), 'body' => $l->body, 'parts' => $l->parts,
                'status' => $l->status, 'error' => $l->error, 'provider' => $l->provider, 'created_at' => $l->created_at->toIso8601String(),
            ])->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function audience(Request $request): JsonResponse
    {
        $request->validate(SmsCampaignRequest::audienceRules());
        $audience = SmsCampaignRequest::audienceFrom((array) $request->input('audience', []));

        return response()->json(['data' => ['count' => Audience::count($audience)]]);
    }

    public function storeCampaign(SmsCampaignRequest $request, ManageSmsCampaigns $manage): JsonResponse
    {
        return response()->json(['data' => $this->campaign($manage->save(null, $request->campaign(), (string) $request->user()?->getAuthIdentifier()))], 201);
    }

    public function updateCampaign(SmsCampaignRequest $request, SmsCampaign $smsCampaign, ManageSmsCampaigns $manage): JsonResponse
    {
        return response()->json(['data' => $this->campaign($manage->save($smsCampaign, $request->campaign(), null))]);
    }

    public function scheduleCampaign(Request $request, SmsCampaign $smsCampaign, ManageSmsCampaigns $manage): JsonResponse
    {
        $at = $request->validate(['send_at' => ['nullable', 'date']])['send_at'] ?? null;

        return response()->json(['data' => $this->campaign($manage->schedule($smsCampaign, $at === null ? null : (string) $at))]);
    }

    public function cancelCampaign(SmsCampaign $smsCampaign, ManageSmsCampaigns $manage): JsonResponse
    {
        return response()->json(['data' => $this->campaign($manage->cancel($smsCampaign))]);
    }

    /** @return array<string, mixed> credentials never leave: secrets only as a masked hint */
    private function account(SmsAccount $a): array
    {
        $spec = SmsDrivers::DRIVERS[$a->provider]['fields'] ?? [];
        $fields = [];
        foreach ($spec as $key => $field) {
            $value = (string) ($a->credentials[$key] ?? '');
            $fields[$key] = $field['secret']
                ? ['is_set' => $value !== '', 'masked' => $value === '' ? null : '••••'.mb_substr($value, -4), 'value' => null]
                : ['is_set' => $value !== '', 'masked' => null, 'value' => $value];
        }

        return [
            'provider' => $a->provider, 'fields' => $fields, 'is_active' => $a->is_active,
            'verified_at' => $a->verified_at?->toIso8601String(), 'last_error' => $a->last_error,
        ];
    }

    /** @return array<string, mixed> */
    private function campaign(SmsCampaign $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'body' => $c->body, 'audience' => $c->audience, 'status' => $c->status,
            'parts' => SmsText::parts($c->body.SmsText::OPT_OUT_FOOTER),
            'scheduled_at' => $c->scheduled_at?->toIso8601String(), 'started_at' => $c->started_at?->toIso8601String(), 'finished_at' => $c->finished_at?->toIso8601String(),
            'recipients' => $c->recipients, 'sent' => $c->sent, 'failed' => $c->failed, 'created_at' => $c->created_at->toIso8601String(),
        ];
    }

    private function mask(string $phone): string
    {
        $local = PhoneNormalizer::toLocal($phone);

        return mb_substr($local, 0, 4).'***'.mb_substr($local, -4);
    }
}
