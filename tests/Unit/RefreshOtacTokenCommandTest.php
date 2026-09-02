<?php

namespace Modules\Insurance\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Insurance\Settings\InsuranceSettings;
use Tests\TestCase;

class RefreshOtacTokenCommandTest extends TestCase
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

    public function test_skips_when_otac_is_not_configured(): void
    {
        $this->artisan('insurance:otac-refresh-token')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_refreshes_and_caches_the_token_when_configured(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->otac_enabled = true;
        $settings->otac_username = '0245426972';
        $settings->otac_password = 'secret';
        $settings->save();

        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response([
                'accessToken' => ['token' => 'cron-token', 'expiresIn' => 7200],
            ]),
        ]);

        $this->artisan('insurance:otac-refresh-token')->assertExitCode(0);

        $this->assertSame('cron-token', Cache::get('insurance_otac_access_token'));
    }

    public function test_fails_when_login_is_rejected(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->otac_enabled = true;
        $settings->otac_username = '0245426972';
        $settings->otac_password = 'wrong';
        $settings->save();

        Http::fake(['otac.nhia.gov.gh/api/login' => Http::response([], 401)]);

        $this->artisan('insurance:otac-refresh-token')->assertExitCode(1);
    }
}
