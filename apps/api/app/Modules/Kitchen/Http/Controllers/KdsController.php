<?php

namespace App\Modules\Kitchen\Http\Controllers;

use App\Modules\Commerce\Actions\Tables\TableSessions;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\TableSessionRequest;
use App\Modules\Core\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Modules\Kitchen\Actions\UpdateKitchenItems;
use App\Modules\Kitchen\Enums\KitchenItemStatus;
use App\Modules\Kitchen\Events\KitchenBoardChanged;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Kitchen\Support\KitchenActor;
use App\Modules\Kitchen\Support\KitchenBoard;
use App\Support\Realtime\LiveVersion;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The kitchen screen's API. Callers are paired devices or staff with kds.operate. */
final class KdsController
{
    public function me(Request $request): JsonResponse
    {
        $actor = KitchenActor::from($request);
        $user = $request->user('sanctum');

        $branches = Branch::query()
            ->when($actor->branchId, fn ($q, $id) => $q->whereKey($id))
            ->whereIn('id', KitchenStation::query()->where('is_active', true)->select('branch_id'))
            ->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => [
            'actor' => [
                'type' => $actor->type,
                'name' => $user instanceof KitchenDevice ? $user->name : ($user instanceof User ? $user->name : null),
                'station_id' => $actor->stationId,
            ],
            'branches' => $branches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'stations' => KitchenStation::query()->where('branch_id', $b->id)->where('is_active', true)
                    ->when($actor->stationId, fn ($q, $id) => $q->whereKey($id))
                    ->orderByDesc('is_default')->orderBy('sort')->get(['id', 'name', 'late_after_minutes']),
            ])->values(),
        ]]);
    }

    /** Polled every few seconds: unchanged boards cost a 304 without a body. */
    public function board(Request $request, TenantContext $context): JsonResponse|Response
    {
        $actor = KitchenActor::from($request);
        $v = $request->validate(['branch_id' => ['nullable', 'string', 'max:26'], 'station_id' => ['nullable', 'string', 'max:26']]);

        $branchId = $actor->branchId ?? ($v['branch_id'] ?? null)
            ?? KitchenStation::query()->where('is_active', true)->orderBy('sort')->value('branch_id');

        if (! is_string($branchId) || ! $actor->canReach($branchId, $v['station_id'] ?? null)) {
            throw new NotFoundHttpException;
        }

        // Cheap path: same version, same minute (recently-finished orders age out by the minute),
        // same view → nothing changed; no board queries at all.
        $etag = sha1(implode('|', [LiveVersion::get(LiveVersion::kitchen($context->require()->id, $branchId)), intdiv(time(), 60), $branchId, $actor->stationId, $v['station_id'] ?? '']));
        if (trim((string) $request->header('If-None-Match'), '"') === $etag) {
            return response()->noContent(304)->setEtag($etag);
        }

        $stationIds = KitchenStation::query()->where('branch_id', $branchId)->where('is_active', true)
            ->when($actor->stationId ?? ($v['station_id'] ?? null), fn ($q, $id) => $q->whereKey($id))
            ->pluck('id')->all();

        $board = KitchenBoard::build($branchId, $stationIds);

        return response()->json(['data' => [...$board, 'branch_id' => $branchId, 'server_time' => now()->toIso8601String()]])
            ->setEtag($etag);
    }

    public function start(Request $request, string $kitchenItem, UpdateKitchenItems $update): JsonResponse
    {
        return $this->move($request, $kitchenItem, KitchenItemStatus::Preparing, $update);
    }

    public function ready(Request $request, string $kitchenItem, UpdateKitchenItems $update): JsonResponse
    {
        return $this->move($request, $kitchenItem, KitchenItemStatus::Ready, $update);
    }

    public function recall(Request $request, string $kitchenItem, UpdateKitchenItems $update): JsonResponse
    {
        return $this->move($request, $kitchenItem, KitchenItemStatus::Preparing, $update);
    }

    public function bump(Request $request, string $kdsOrder, UpdateKitchenItems $update): JsonResponse
    {
        $stationId = (string) $request->validate(['station_id' => ['required', 'string', 'max:26']])['station_id'];
        $order = $update->bump(Order::query()->findOrFail($kdsOrder), $stationId, KitchenActor::from($request));

        return response()->json(['data' => ['order_status' => $order->status->value, 'order_status_label' => $order->status->label()]]);
    }

    public function acknowledge(Request $request, string $tableRequest, TableSessions $sessions): JsonResponse
    {
        $actor = KitchenActor::from($request);
        $model = TableSessionRequest::query()->with('table')->findOrFail($tableRequest);

        if (! $actor->canReach($model->table->branch_id)) {
            throw new NotFoundHttpException;
        }

        $user = $request->user('sanctum');
        $sessions->acknowledge($model, $user instanceof User ? $user : null);
        KitchenBoardChanged::dispatch($model->tenant_id, $model->table->branch_id);

        return response()->json(['data' => ['id' => $model->id, 'status' => $model->status]]);
    }

    private function move(Request $request, string $itemId, KitchenItemStatus $to, UpdateKitchenItems $update): JsonResponse
    {
        $item = $update->item(KitchenItem::query()->findOrFail($itemId), $to, KitchenActor::from($request));

        return response()->json(['data' => [
            'id' => $item->id,
            'status' => $item->status->value,
            'order_status' => $item->order->status->value,
            'order_status_label' => $item->order->status->label(),
        ]]);
    }
}
