<?php

namespace Tests\Feature\Messaging;

use App\Support\Sms\SmsDrivers;
use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Request shape and success/failure parsing of every SMS panel adapter. */
final class SmsDriversTest extends TestCase
{
    private function msg(): SmsMessage
    {
        return new SmsMessage(['+989121234567', '+989351112233'], 'سلام');
    }

    public function test_melipayamak(): void
    {
        Http::fake(['rest.payamak-panel.com/*' => Http::sequence()->push(['Value' => '1234567', 'RetStatus' => 1])->push(['Value' => '0', 'RetStatus' => 35])]);
        $sms = SmsDrivers::make('melipayamak', ['username' => 'u', 'password' => 'p', 'sender' => '5000']);
        $ok = $sms->send($this->msg());
        $this->assertTrue($ok->successful);
        $this->assertSame('1234567', $ok->providerReference);
        Http::assertSent(fn (Request $r) => $r['to'] === '09121234567,09351112233' && $r['from'] === '5000' && $r['username'] === 'u');
        $this->assertSame('provider_status_35', $sms->send($this->msg())->error);
    }

    public function test_smsir(): void
    {
        Http::fake(['api.sms.ir/*' => Http::sequence()->push(['status' => 1, 'data' => ['packId' => 'abc']])->push(['status' => 0, 'message' => 'x'], 400)]);
        $sms = SmsDrivers::make('smsir', ['api_key' => 'KEY', 'sender' => '30004505']);
        $this->assertSame('abc', $sms->send($this->msg())->providerReference);
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-api-key', 'KEY') && $r['lineNumber'] === 30004505 && $r['mobiles'] === ['09121234567', '09351112233']);
        $this->assertFalse($sms->send($this->msg())->successful);
    }

    public function test_ippanel(): void
    {
        Http::fake(['api2.ippanel.com/*' => Http::sequence()->push(['status' => 'OK', 'data' => ['message_id' => 99]])->push(['status' => 'ERROR', 'code' => 422], 422)]);
        $sms = SmsDrivers::make('ippanel', ['api_key' => 'K', 'sender' => '+983000505']);
        $this->assertSame('99', $sms->send($this->msg())->providerReference);
        Http::assertSent(fn (Request $r) => $r->hasHeader('apikey', 'K') && $r['sender'] === '+983000505' && $r['recipient'] === ['09121234567', '09351112233']);
        $this->assertFalse($sms->send($this->msg())->successful);
    }

    public function test_kavenegar(): void
    {
        Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 200], 'entries' => [['messageid' => 7]]])]);
        $this->assertTrue(SmsDrivers::make('kavenegar', ['api_key' => 'KK', 'sender' => ''])->send($this->msg())->successful);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/KK/sms/send.json') && $r['receptor'] === '09121234567,09351112233');
    }

    public function test_raygan_text_and_platform_login_code(): void
    {
        Http::fake([
            'raygansms.com/*' => Http::response('2451871', 200),
            'smspanel.trez.ir/*' => Http::sequence()->push('1587', 200)->push('8', 200),
        ]);
        $cafe = SmsDrivers::make('raygan', ['username' => 'u', 'password' => 'p', 'sender' => '5000']);
        $this->assertTrue($cafe->send($this->msg())->successful);
        Http::assertSentCount(2); // one request per recipient
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'SendMessageWithPost.ashx') && $r['RecNumber'] === '09121234567' && $r['PhoneNumber'] === '5000');

        // Login codes: the platform line through Trez's OTP service.
        config(['sms.default' => 'raygan', 'sms.providers.raygan.username' => 'plat', 'sms.providers.raygan.password' => 'pw']);
        $this->app->forgetInstance(SmsProvider::class);
        $platform = $this->app->make(SmsProvider::class);
        $this->assertTrue($platform->sendVerificationCode('+989121234567', '12345')->successful);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'SendMessageWithCode.ashx') && str_contains(urldecode($r->url()), '12345') && str_contains($r->url(), 'Mobile=09121234567'));
        $this->assertSame('provider_status_8', $platform->sendVerificationCode('+989121234567', '12345')->error); // bad credentials
    }

    public function test_connection_failures_never_throw(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));
        foreach (['melipayamak', 'smsir', 'ippanel', 'raygan'] as $driver) {
            $result = SmsDrivers::make($driver, ['username' => 'u', 'password' => 'p', 'api_key' => 'k', 'sender' => '1'])->send($this->msg());
            $this->assertSame('connection', $result->error, $driver);
        }
    }
}
