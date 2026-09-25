<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Modules\Advertising\Actions\RecordAdEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Impression/click beacons for served ads. Always 204: whether it counted is not disclosed. */
final class PublicAdEventController
{
    public function store(Request $request, RecordAdEvent $record): Response
    {
        $v = $request->validate([
            'token' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(RecordAdEvent::TYPES)],
        ]);
        $record->handle((string) $v['token'], (string) $v['type'], (string) $request->ip(), (string) $request->userAgent());

        return response()->noContent();
    }
}
