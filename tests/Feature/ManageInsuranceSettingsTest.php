<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Insurance\Filament\Clusters\Insurance\Pages\ManageInsuranceSettings;
use Modules\Insurance\Settings\InsuranceSettings;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManageInsuranceSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Insurance']);
    }

    private function adminWithSettingsAccess(): User
    {
        Permission::findOrCreate('View ManageInsuranceSettings', 'web');

        return User::factory()->create()->givePermissionTo('View ManageInsuranceSettings');
    }

    public function test_nhis_toggles_are_present_and_persist(): void
    {
        $admin = $this->adminWithSettingsAccess();

        Livewire::actingAs($admin)
            ->test(ManageInsuranceSettings::class)
            ->assertFormFieldExists('nhis_enabled')
            ->assertFormFieldExists('require_claim_check_code')
            ->fillForm([
                'nhis_enabled' => false,
                'require_claim_check_code' => true,
            ])
            ->call('save');

        $settings = app(InsuranceSettings::class);

        $this->assertFalse($settings->nhis_enabled);
        $this->assertTrue($settings->require_claim_check_code);

        $this->assertDatabaseHas('settings', [
            'group' => 'insurance',
            'name' => 'nhis_enabled',
        ]);

        $this->assertDatabaseHas('settings', [
            'group' => 'insurance',
            'name' => 'require_claim_check_code',
        ]);

        Livewire::actingAs($admin)
            ->test(ManageInsuranceSettings::class)
            ->fillForm([
                'nhis_enabled' => true,
                'require_claim_check_code' => false,
            ])
            ->call('save');

        $settings = app(InsuranceSettings::class);

        $this->assertTrue($settings->nhis_enabled);
        $this->assertFalse($settings->require_claim_check_code);
    }

    public function test_otac_credentials_persist_and_password_is_encrypted_at_rest(): void
    {
        $admin = $this->adminWithSettingsAccess();

        Livewire::actingAs($admin)
            ->test(ManageInsuranceSettings::class)
            ->assertFormFieldExists('otac_enabled')
            ->assertFormFieldExists('otac_username')
            ->assertFormFieldExists('otac_password')
            ->fillForm([
                'otac_enabled' => true,
                'otac_username' => '0245426972',
                'otac_password' => 'super-secret',
            ])
            ->call('save');

        $settings = app(InsuranceSettings::class);

        $this->assertTrue($settings->otac_enabled);
        $this->assertSame('0245426972', $settings->otac_username);
        $this->assertSame('super-secret', $settings->otac_password);

        $storedPayload = DB::table('settings')
            ->where('group', 'insurance')
            ->where('name', 'otac_password')
            ->value('payload');

        $this->assertNotNull($storedPayload);
        $this->assertStringNotContainsString('super-secret', (string) $storedPayload);
    }

    public function test_otac_connection_action_reports_the_facility(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->otac_enabled = true;
        $settings->otac_username = '0245426972';
        $settings->otac_password = 'super-secret';
        $settings->save();

        Http::preventStrayRequests();
        Http::fake([
            'otac.nhia.gov.gh/api/login' => Http::response([
                'accessToken' => ['token' => 'jwt-token', 'expiresIn' => 7200],
            ]),
            'otac.nhia.gov.gh/api/attendance/hpinfo' => Http::response([
                'hpn' => '9947',
                'hpName' => 'FAAKO MEDICAL CONSULT',
            ]),
        ]);

        Livewire::actingAs($this->adminWithSettingsAccess())
            ->test(ManageInsuranceSettings::class)
            ->callAction('testOtacConnection')
            ->assertNotified('OTAC connection OK');
    }

    public function test_otac_connection_action_reports_login_failure(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->otac_enabled = true;
        $settings->otac_username = '0245426972';
        $settings->otac_password = 'wrong';
        $settings->save();

        Http::preventStrayRequests();
        Http::fake(['otac.nhia.gov.gh/api/login' => Http::response([], 401)]);

        Livewire::actingAs($this->adminWithSettingsAccess())
            ->test(ManageInsuranceSettings::class)
            ->callAction('testOtacConnection')
            ->assertNotified('OTAC login failed');
    }
}
