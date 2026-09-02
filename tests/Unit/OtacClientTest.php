<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Insurance\Services\Otac\OtacClient;
use Modules\Insurance\Settings\InsuranceSettings;
use Tests\TestCase;

class OtacClientTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Insurance']);

        Http::preventStrayRequests();
        Cache::forget('insurance_otac_access_token');
    }

    protected function tearDown(): void
    {
        Cache::forget('insurance_otac_access_token');
        parent::tearDown();
    }

    protected function configureOtac(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->otac_enabled = true;
        $settings->otac_username = '0245426972';
        $settings->otac_password = 'secret';
        $settings->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginResponse(string $token = 'jwt-token'): array
    {
        return [
            'accessToken' => ['token' => $token, 'expiresIn' => 7200],
            'refreshToken' => ['token' => 'refresh', 'expiresIn' => 7200],
        ];
    }

    public function test_is_not_configured_by_default(): void
    {
        $this->assertFalse(app(OtacClient::class)->isConfigured());
    }

    public function test_is_configured_with_enabled_toggle_and_credentials(): void
    {
        $this->configureOtac();

        $this->assertTrue(app(OtacClient::class)->isConfigured());
    }

    public function test_login_caches_token_and_reuses_it(): void
    {
        $this->configureOtac();
        Http::fake(['otac.nhia.gov.gh/api/login' => Http::response($this->loginResponse())]);

        $client = app(OtacClient::class);

        $this->assertSame('jwt-token', $client->login());
        $this->assertSame('jwt-token', $client->accessToken());
        $this->assertSame('jwt-token', Cache::get('insurance_otac_access_token'));
        Http::assertSentCount(1);
    }

    public function test_login_failure_returns_null(): void
    {
        $this->configureOtac();
        Http::fake(['otac.nhia.gov.gh/api/login' => Http::response([], 500)]);

        $this->assertNull(app(OtacClient::class)->login());
    }

    public function test_generate_attendance_returns_decoded_success_payload(): void
    {
        $this->configureOtac();
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response($this->loginResponse()),
            'otac.nhia.gov.gh/api/attendance/generate' => Http::response([
                'attendanceData' => ['ccc' => '57566', 'memberName' => 'ABDUL HAKIM  YAHUZA'],
                'statusCode' => 0,
                'statusMessage' => 'Attendance Generated Successfully',
            ]),
        ]);

        $response = app(OtacClient::class)->generateAttendance('NHISCARD', '18014180');

        $this->assertSame(0, $response['statusCode']);
        $this->assertSame('57566', $response['attendanceData']['ccc']);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/attendance/generate')
                ? $request['cardType'] === 'NHISCARD' && $request['cardNo'] === '18014180'
                : true;
        });
    }

    public function test_generate_attendance_transport_error_maps_to_failure_payload(): void
    {
        $this->configureOtac();
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response($this->loginResponse()),
            'otac.nhia.gov.gh/api/attendance/generate' => Http::response([], 500),
        ]);

        $response = app(OtacClient::class)->generateAttendance('NHISCARD', '18014180');

        $this->assertSame(-1, $response['statusCode']);
        $this->assertNull($response['attendanceData']);
    }

    public function test_expired_token_triggers_one_relogin_and_retry(): void
    {
        $this->configureOtac();
        Cache::put('insurance_otac_access_token', 'stale-token', now()->addHour());

        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response($this->loginResponse('fresh-token')),
            'otac.nhia.gov.gh/api/attendance/generate' => Http::sequence()
                ->push([], 401)
                ->push(['attendanceData' => ['ccc' => '57566'], 'statusCode' => 0, 'statusMessage' => 'OK']),
        ]);

        $response = app(OtacClient::class)->generateAttendance('NHISCARD', '18014180');

        $this->assertSame(0, $response['statusCode']);
        $this->assertSame('fresh-token', Cache::get('insurance_otac_access_token'));
    }

    public function test_hp_info_reports_facility(): void
    {
        $this->configureOtac();
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response($this->loginResponse()),
            'otac.nhia.gov.gh/api/attendance/hpinfo' => Http::response(['hpn' => '9947', 'hpName' => 'FAAKO MEDICAL CONSULT']),
        ]);

        $info = app(OtacClient::class)->hpInfo();

        $this->assertTrue($info['ok']);
        $this->assertSame('9947', data_get($info['body'], 'hpn'));
    }
}
