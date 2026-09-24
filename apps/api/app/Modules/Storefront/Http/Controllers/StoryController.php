<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Modules\Storefront\Actions\ManageStories;
use App\Modules\Storefront\Http\Requests\StoryRequest;
use App\Modules\Storefront\Http\Resources\StoryResource;
use App\Modules\Storefront\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Staff side of stories: live and scheduled first, then the recently expired (last 30 days). */
final class StoryController
{
    public function index(): AnonymousResourceCollection
    {
        return StoryResource::collection(
            Story::query()->where('ends_at', '>', now()->subDays(30))
                ->orderByRaw('CASE WHEN ends_at > ? THEN 0 ELSE 1 END', [now()])
                ->orderBy('sort')->orderByDesc('created_at')
                ->get(),
        );
    }

    public function store(StoryRequest $request, ManageStories $stories): JsonResponse
    {
        return (new StoryResource($stories->save($request->safe()->except('image'), $request->file('image'))))->response()->setStatusCode(201);
    }

    public function update(StoryRequest $request, Story $story, ManageStories $stories): StoryResource
    {
        return new StoryResource($stories->save($request->safe()->except('image'), $request->file('image'), $story));
    }

    public function reorder(Request $request, ManageStories $stories): Response
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['string', 'size:26']])['ids'];
        $stories->reorder(array_values($ids));

        return response()->noContent();
    }

    public function destroy(Story $story, ManageStories $stories): Response
    {
        $stories->delete($story);

        return response()->noContent();
    }
}
