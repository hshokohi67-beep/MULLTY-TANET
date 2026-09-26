<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Storefront\Models\StorefrontLanding;
use App\Modules\Storefront\Support\LandingPresenter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The published landing page of the current café (404 while there is none: the menu is home). */
final class PublicLandingController
{
    public function show(LandingPresenter $presenter): JsonResponse
    {
        $landing = StorefrontLanding::query()->where('is_published', true)->first() ?? throw new NotFoundHttpException;

        return response()->json(['data' => $presenter->public($landing)])
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
    }
}
