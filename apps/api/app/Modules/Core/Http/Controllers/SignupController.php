<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\SignUpCafe;
use App\Modules\Core\Http\Requests\SignupRequest;
use App\Modules\Core\Support\SlugSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «شروع رایگان»: address suggestions, the mobile code, and creating the café (owner signed in). */
final class SignupController
{
    public function slug(Request $request): JsonResponse
    {
        $input = $request->validate(['name' => ['nullable', 'string', 'max:80'], 'slug' => ['nullable', 'string', 'max:30']]);
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));

        return response()->json(['data' => [
            'suggestion' => SlugSuggester::suggest((string) ($input['name'] ?? '')),
            'slug' => $slug !== '' ? $slug : null,
            'valid' => $slug !== '' && SlugSuggester::valid($slug),
            'available' => $slug !== '' && SlugSuggester::available($slug),
            'base' => config('tenancy.subdomain_base'),
        ]]);
    }

    public function otp(SignupRequest $request, SignUpCafe $signup): JsonResponse
    {
        return response()->json(['data' => $signup->sendCode($request->phoneE164())]);
    }

    public function store(SignupRequest $request, SignUpCafe $signup): JsonResponse
    {
        $result = $signup->handle([
            'cafe_name' => trim((string) $request->validated('cafe_name')),
            'slug' => (string) $request->validated('slug'),
            'owner_name' => trim((string) $request->validated('owner_name')),
            'phone' => $request->phoneE164(),
            'password' => (string) $request->validated('password'),
            'code' => (string) $request->validated('code'),
        ]);

        return response()->json(['data' => [
            'token' => $result['token'],
            'tenant' => ['slug' => $result['tenant']->slug, 'name' => $result['tenant']->name],
        ]], 201);
    }
}
