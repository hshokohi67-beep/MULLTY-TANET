<?php

namespace App\Modules\Insights\Http\Controllers;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Modules\Insights\Models\DashboardLayout;
use App\Modules\Insights\Models\ShiftNote;
use App\Modules\Insights\Support\Overview;
use App\Modules\Insights\Support\WidgetCatalog;
use App\Modules\Insights\Support\WidgetData;
use App\Support\Realtime\LiveVersion;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantExists;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The customisable dashboard: per-user layout, per-widget data, shift notes. */
final class DashboardController
{
    public function layout(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $can = $this->can();
        $saved = DashboardLayout::query()->where('user_id', $user->id)->first();

        return response()->json(['data' => [
            'widgets' => $saved ? WidgetCatalog::visible($saved->widgets, $can) : WidgetCatalog::defaultFor($this->roleKeys($user), $can),
            'is_default' => $saved === null,
            'catalog' => collect(WidgetCatalog::all())
                ->filter(fn (array $w) => $can($w['permission']))
                ->map(fn (array $w, string $key) => ['key' => $key, 'title' => $w['title'], 'description' => $w['description'], 'sizes' => $w['sizes'], 'default_size' => $w['default_size']])
                ->values(),
        ]]);
    }

    public function saveLayout(Request $request): JsonResponse
    {
        $v = $request->validate([
            'widgets' => ['present', 'array', 'max:30'],
            'widgets.*.key' => ['required', 'string', Rule::in(array_keys(WidgetCatalog::all()))],
            'widgets.*.size' => ['required', Rule::in(WidgetCatalog::SIZES)],
        ]);

        /** @var list<array{key: string, size: string}> $widgets */
        $widgets = array_values($v['widgets']);
        $user = $this->user($request);
        $clean = WidgetCatalog::visible($widgets, $this->can());

        DashboardLayout::query()->updateOrCreate(['user_id' => $user->id], ['widgets' => $clean]);

        return response()->json(['data' => ['widgets' => $clean, 'is_default' => false], 'message' => __('messages.saved')]);
    }

    public function resetLayout(Request $request): Response
    {
        DashboardLayout::query()->where('user_id', $this->user($request)->id)->delete();

        return response()->noContent();
    }

    /** One widget's data, cached briefly on the orders live version (like the overview). */
    public function widget(Request $request, string $widget, TenantContext $context): JsonResponse
    {
        $key = $widget;
        $def = WidgetCatalog::all()[$key] ?? throw new NotFoundHttpException;
        abort_unless(Gate::allows($def['permission']), 403);

        $v = $request->validate([
            'range' => ['nullable', Rule::in(Overview::RANGES)],
            'branch_id' => ['nullable', 'string', 'max:26'],
        ]);
        $tenant = $context->require();
        $range = $v['range'] ?? 'today';
        $branchId = $v['branch_id'] ?? null;

        // Shift notes change on their own; everything else follows order activity (and the minute).
        $version = $key === 'shift_notes' ? (string) Cache::get("live:{$tenant->id}:notes", '0') : LiveVersion::get(LiveVersion::orders($tenant->id));
        $cacheKey = sprintf('widget:%s:%s:%s:%s:%s:%d', $tenant->id, $key, $branchId ?? 'all', $range, $version, intdiv(time(), 60));

        $data = Cache::remember($cacheKey, 90, fn () => (new WidgetData($tenant->timezone, $branchId, CarbonImmutable::now()))->get($key, $range));

        return response()->json(['data' => $data]);
    }

    public function storeNote(Request $request, TenantContext $context): JsonResponse
    {
        $v = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'branch_id' => ['nullable', 'string', TenantExists::in('branches')],
        ]);
        $note = ShiftNote::query()->create(['body' => $v['body'], 'branch_id' => $v['branch_id'] ?? null, 'author_id' => $this->user($request)->id]);
        Cache::put("live:{$context->require()->id}:notes", (string) $note->id, 86_400);

        return response()->json(['data' => ['id' => $note->id]], 201);
    }

    public function destroyNote(Request $request, string $shiftNote, TenantContext $context): Response
    {
        $model = ShiftNote::query()->findOrFail($shiftNote);
        // Authors remove their own notes; managers of the team may clean up any.
        abort_unless($model->author_id === $this->user($request)->id || Gate::allows('team.manage'), 403);
        $model->delete();
        Cache::put("live:{$context->require()->id}:notes", 'd'.$model->id, 86_400);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** @return Closure(string): bool */
    private function can(): Closure
    {
        return fn (string $ability) => Gate::allows($ability);
    }

    /** @return list<string> */
    private function roleKeys(User $user): array
    {
        $member = TenantUser::query()->where('user_id', $user->id)->with('roles:id,key')->first();

        return $member ? $member->roles->pluck('key')->map(fn ($k) => (string) $k)->values()->all() : [];
    }
}
