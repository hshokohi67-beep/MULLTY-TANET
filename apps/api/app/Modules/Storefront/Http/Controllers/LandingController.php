<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Storefront\Actions\ManageLanding;
use App\Modules\Storefront\Http\Requests\LandingMediaRequest;
use App\Modules\Storefront\Http\Requests\LandingRequest;
use App\Modules\Storefront\Models\StorefrontMedia;
use App\Modules\Storefront\Support\LandingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

/** Staff side of the landing page (its editor in the panel). */
final class LandingController
{
    public function show(ManageLanding $landing, LandingPresenter $presenter): JsonResponse
    {
        return response()->json(['data' => $presenter->staff($landing->current())]);
    }

    public function update(LandingRequest $request, ManageLanding $landing, LandingPresenter $presenter): JsonResponse
    {
        return response()->json(['data' => $presenter->staff($landing->save($request->validated()))]);
    }

    public function storeMedia(LandingMediaRequest $request, ManageLanding $landing, LandingPresenter $presenter): JsonResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $caption = $request->validated('caption');
        $landing->addMedia((string) $request->validated('kind'), $file, is_string($caption) && trim($caption) !== '' ? trim($caption) : null);

        return response()->json(['data' => $presenter->staff($landing->current())], 201);
    }

    public function updateMedia(Request $request, StorefrontMedia $landingMedia, ManageLanding $landing): Response
    {
        $caption = $request->validate(['caption' => ['nullable', 'string', 'max:120']])['caption'] ?? null;
        $landing->caption($landingMedia, is_string($caption) && trim($caption) !== '' ? trim($caption) : null);

        return response()->noContent();
    }

    public function destroyMedia(StorefrontMedia $landingMedia, ManageLanding $landing): Response
    {
        $landing->deleteMedia($landingMedia);

        return response()->noContent();
    }

    public function reorderMedia(Request $request, ManageLanding $landing): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:'.StorefrontMedia::MAX_GALLERY], 'ids.*' => ['string', 'size:26']])['ids'];
        $landing->reorder(array_values($ids));

        return response()->noContent();
    }
}
