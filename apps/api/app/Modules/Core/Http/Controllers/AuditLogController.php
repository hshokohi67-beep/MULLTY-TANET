<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Http\Resources\AuditLogResource;
use App\Modules\Core\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditLogController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['action' => ['nullable', 'string', 'max:100']]);

        return AuditLogResource::collection(
            AuditLog::query()
                ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
                ->latest('created_at')
                ->latest('id')
                ->cursorPaginate(30),
        );
    }
}
