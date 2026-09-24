<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\ManageCategoryImage;
use App\Modules\Catalog\Actions\SaveCategory;
use App\Modules\Catalog\Http\Requests\CategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class CategoryController
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->withCount('products')->orderBy('sort')->orderBy('name')->get());
    }

    public function store(CategoryRequest $request, SaveCategory $save): JsonResponse
    {
        return (new CategoryResource($save->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category, SaveCategory $save): CategoryResource
    {
        return new CategoryResource($save->handle($request->validated(), $category));
    }

    public function uploadImage(Request $request, Category $category, ManageCategoryImage $images): CategoryResource
    {
        $request->validate(['image' => ['required', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(8 * 1024)
            ->dimensions(Rule::dimensions()->minWidth(120)->minHeight(120)->maxWidth(8000)->maxHeight(8000))]], [], ['image' => 'تصویر دسته']);

        return new CategoryResource($images->upload($category, $request->file('image')));
    }

    public function deleteImage(Category $category, ManageCategoryImage $images): CategoryResource
    {
        return new CategoryResource($images->remove($category));
    }

    public function destroy(Category $category, DeleteCategory $delete): Response
    {
        $delete->handle($category);

        return response()->noContent();
    }
}
