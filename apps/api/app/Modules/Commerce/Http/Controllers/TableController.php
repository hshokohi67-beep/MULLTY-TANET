<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Actions\Tables\ManageTableQr;
use App\Modules\Commerce\Actions\Tables\TableSessions;
use App\Modules\Commerce\Http\Requests\TableRequest;
use App\Modules\Commerce\Http\Resources\TableRequestResource;
use App\Modules\Commerce\Http\Resources\TableResource;
use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Commerce\Models\TableSessionRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class TableController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $branchId = $request->validate(['branch_id' => ['nullable', 'string', 'max:26']])['branch_id'] ?? null;

        return TableResource::collection(
            RestaurantTable::query()
                ->with(['qrCodes' => fn ($q) => $q->where('is_active', true), 'sessions' => fn ($q) => $q->where('status', 'open')])
                ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
                ->orderBy('sort')->orderBy('label')
                ->get(),
        );
    }

    public function store(TableRequest $request, AuditLogger $audit): JsonResponse
    {
        $table = RestaurantTable::query()->create($request->validated());
        $audit->record('table.created', $table, $request->validated());

        return (new TableResource($table))->response()->setStatusCode(201);
    }

    public function update(TableRequest $request, RestaurantTable $table, AuditLogger $audit): TableResource
    {
        $table->fill($request->validated());
        $changes = $table->getDirty();
        $table->save();
        $audit->record('table.updated', $table, $changes);

        return new TableResource($table->load(['qrCodes' => fn ($q) => $q->where('is_active', true)]));
    }

    /** Issues (or re-issues) the table's QR code. The raw token is in this response only. */
    public function issueQr(RestaurantTable $table, ManageTableQr $qr): JsonResponse
    {
        ['code' => $code, 'token' => $token] = $qr->issue($table);

        return response()->json(['data' => [
            'qr_token' => $token,
            'hint' => $code->token_hint,
            'message' => __('messages.qr_issued'),
        ]], 201);
    }

    public function closeSession(Request $request, RestaurantTable $table, TableSessions $sessions): Response
    {
        OrderSession::query()->where('table_id', $table->getKey())->where('status', 'open')->get()
            ->each(fn (OrderSession $s) => $sessions->close($s, $request->user()));

        return response()->noContent();
    }

    public function requests(Request $request): AnonymousResourceCollection
    {
        return TableRequestResource::collection(
            TableSessionRequest::query()->with('table')->where('status', 'open')->oldest('created_at')->limit(100)->get(),
        );
    }

    public function acknowledge(Request $request, string $tableRequest, TableSessions $sessions): TableRequestResource
    {
        $model = TableSessionRequest::query()->with('table')->findOrFail($tableRequest);

        return new TableRequestResource($sessions->acknowledge($model, $request->user()));
    }
}
