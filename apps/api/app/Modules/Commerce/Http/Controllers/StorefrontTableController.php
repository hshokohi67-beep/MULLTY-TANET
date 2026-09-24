<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Actions\Tables\ManageTableQr;
use App\Modules\Commerce\Actions\Tables\TableSessions;
use App\Modules\Commerce\Enums\TableRequestType;
use App\Modules\Commerce\Http\Requests\JoinTableRequest;
use App\Modules\Commerce\Http\Requests\TableServiceRequest;
use Illuminate\Http\JsonResponse;

/**
 * QR flow: scan → secure token → table → session. Tokens travel in the request body/headers,
 * never in logged URLs.
 */
final class StorefrontTableController
{
    public function join(JoinTableRequest $request, ManageTableQr $qr, TableSessions $sessions): JsonResponse
    {
        $code = $qr->resolve((string) $request->validated('qr_token'));
        $session = $sessions->join($code->table);

        return response()->json(['data' => [
            'session_token' => $session->accessToken(),
            'table' => ['id' => $code->table->id, 'label' => $code->table->label],
            'branch' => ['id' => $code->table->branch->id, 'name' => $code->table->branch->name, 'slug' => $code->table->branch->slug],
        ]]);
    }

    public function request(TableServiceRequest $request, TableSessions $sessions): JsonResponse
    {
        $session = $sessions->fromToken($request->header('X-Table-Session'));
        $sessions->request($session, TableRequestType::from($request->validated('type')));

        return response()->json(['message' => __('messages.table_request_sent')], 201);
    }
}
