<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Core\Settings\InsuranceSettings;
use Modules\Insurance\Filament\Clusters\Insurance\Pages\ManageInsuranceSettings;
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
}
