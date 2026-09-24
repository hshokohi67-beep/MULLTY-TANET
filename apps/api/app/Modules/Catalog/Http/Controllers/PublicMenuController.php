<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\BuildPublicMenu;
use App\Modules\Core\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicMenuController
{
    public function show(Request $request, BuildPublicMenu $menu): JsonResponse
    {
        $slug = $request->validate(['branch' => ['nullable', 'string', 'max:64']])['branch'] ?? null;

        $branch = Branch::query()
            ->where('is_active', true)
            ->when($slug, fn ($q) => $q->where('slug', $slug), fn ($q) => $q->orderBy('sort')->orderBy('created_at'))
            ->firstOrFail();

        return response()
            ->json(['data' => $menu->handle($branch)])
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=120');
    }
}
