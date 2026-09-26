<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\ManagePushSubscriptions;
use App\Modules\Notifications\Http\Requests\PushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «اعلان سفارش تازه روی این دستگاه»: a staff member's browser. The endpoint travels in bodies
 * only (it is a capability URL), so these are POSTs.
 */
final class StaffPushController
{
    public function subscribe(PushSubscriptionRequest $request, ManagePushSubscriptions $subscriptions): Response
    {
        $subscriptions->forStaff($this->user($request), $request->keys());

        return response()->noContent();
    }

    public function status(Request $request, ManagePushSubscriptions $subscriptions): JsonResponse
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:1000']])['endpoint'];

        return response()->json(['data' => ['subscribed' => $subscriptions->staffHasDevice($this->user($request), $endpoint)]]);
    }

    public function unsubscribe(Request $request, ManagePushSubscriptions $subscriptions): Response
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:1000']])['endpoint'];
        $subscriptions->removeStaff($this->user($request), $endpoint);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
