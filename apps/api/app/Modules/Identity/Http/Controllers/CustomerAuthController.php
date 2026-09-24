<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Identity\Actions\LoginCustomerWithOtp;
use App\Modules\Identity\Http\Requests\CustomerOtpRequest;
use App\Modules\Identity\Support\OtpService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CustomerAuthController
{
    public function requestOtp(CustomerOtpRequest $request, OtpService $otp, TenantContext $context): JsonResponse
    {
        $timing = $otp->issue($context->require(), $request->phoneE164());

        // Identical response for new and existing customers: no account enumeration.
        return response()->json([
            'message' => __('messages.otp_sent'),
            'expires_in' => $timing['expires_in'],
            'resend_after' => $timing['resend_after'],
        ]);
    }

    public function verifyOtp(CustomerOtpRequest $request, LoginCustomerWithOtp $login, TenantContext $context): JsonResponse
    {
        $result = $login->handle(
            $context->require(),
            $request->phoneE164(),
            (string) $request->validated('code'),
            $request->validated('device_name'),
        );

        return response()->json([
            'token' => $result['token'],
            'is_new' => $result['is_new'],
            'customer' => new CustomerResource($result['customer']),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): CustomerResource
    {
        return new CustomerResource($request->user());
    }
}
