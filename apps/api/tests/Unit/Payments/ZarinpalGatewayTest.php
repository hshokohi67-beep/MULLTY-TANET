<?php

namespace Tests\Unit\Payments;

use App\Modules\Payments\Support\Gateways\ZarinpalGateway;
use Illuminate\Http\Client\Factory;
use Tests\TestCase;

final class ZarinpalGatewayTest extends TestCase
{
    private function gateway(Factory $http): ZarinpalGateway
    {
        return new ZarinpalGateway($http, 'merchant-secret', 'https://zp.test');
    }

    public function test_code_101_already_verified_counts_as_paid(): void
    {
        $http = new Factory;
        $http->fake(['*' => $http->response(['data' => ['code' => 101, 'ref_id' => 77, 'card_pan' => '6037**1234'], 'errors' => []])]);

        $result = $this->gateway($http)->verify(10_000, 'A1');

        $this->assertTrue($result->success);
        $this->assertSame('101', $result->code);
        $this->assertSame('77', $result->refId);
        $this->assertArrayNotHasKey('merchant_id', $result->request);
    }

    public function test_server_errors_and_garbage_are_transient_not_failures(): void
    {
        $http = new Factory;
        $http->fake(['*' => $http->sequence()->push('bad gateway', 502)->push('<html>maintenance</html>', 200)]);

        $first = $this->gateway($http)->verify(10_000, 'A1');
        $second = $this->gateway($http)->verify(10_000, 'A1');

        $this->assertTrue($first->transient);
        $this->assertSame('http_502', $first->code);
        $this->assertTrue($second->transient);
        $this->assertFalse($second->success);
    }

    public function test_request_failure_carries_the_gateway_error_code(): void
    {
        $http = new Factory;
        $http->fake(['*' => $http->response(['data' => [], 'errors' => ['code' => -9, 'message' => 'The input params invalid']], 400)]);

        $result = $this->gateway($http)->request(10_000, 'https://shop.test/cb', 'سفارش');

        $this->assertFalse($result->success);
        $this->assertFalse($result->transient);
        $this->assertSame('-9', $result->code);
        $this->assertNull($result->redirectUrl);
    }
}
