<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\SaveBranch;
use App\Modules\Core\Actions\SyncOpeningHours;
use App\Modules\Core\Http\Requests\BranchRequest;
use App\Modules\Core\Http\Requests\OpeningHoursRequest;
use App\Modules\Core\Http\Resources\BranchResource;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Support\OpeningHoursEvaluator;
use App\Support\Entitlements\EntitlementGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BranchController
{
    public function index(): AnonymousResourceCollection
    {
        return BranchResource::collection(Branch::query()->with('openingHours')->orderBy('sort')->orderBy('name')->get());
    }

    public function store(BranchRequest $request, SaveBranch $save): JsonResponse
    {
        app(EntitlementGate::class)->ensureCanAdd('branches', Branch::query()->count());

        return (new BranchResource($save->handle($request->toData())->load('openingHours')))
            ->response()->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch->load('openingHours'));
    }

    public function update(BranchRequest $request, Branch $branch, SaveBranch $save): BranchResource
    {
        return new BranchResource($save->handle($request->toData(), $branch)->load('openingHours'));
    }

    public function updateOpeningHours(OpeningHoursRequest $request, Branch $branch, SyncOpeningHours $sync): BranchResource
    {
        return new BranchResource($sync->handle($branch, $request->intervals()));
    }

    public function openStatus(Branch $branch, TenantContext $context): JsonResponse
    {
        $timezone = $context->require()->timezone;
        $evaluator = new OpeningHoursEvaluator(
            $branch->openingHours->map(fn ($h) => ['weekday' => $h->weekday, 'opens_at' => $h->opens_at, 'closes_at' => $h->closes_at]),
            $timezone,
        );
        $now = now();

        return response()->json(['data' => [
            'is_open' => $evaluator->isOpenAt($now),
            'next_opening_at' => $evaluator->nextOpeningAfter($now)?->toIso8601String(),
            'timezone' => $timezone,
        ]]);
    }
}
