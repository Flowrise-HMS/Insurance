<?php

namespace Modules\Insurance\Tests\Feature;

use Modules\Core\Enums\NavigationGroup;
use Modules\Insurance\Filament\Clusters\Insurance\Pages\ManageInsuranceSettings;
use Modules\Insurance\Filament\Clusters\Insurance\Pages\NhiaFeedbackImport;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource;
use Tests\TestCase;

/**
 * Master-data resources used Filament's auto-generated labels ("Gdrg Icd
 * Maps", "Members Masters", ...) and the settings/import pages had no group.
 */
class InsuranceNavigationLabelsTest extends TestCase
{
    public function test_master_data_resources_have_readable_labels(): void
    {
        $this->assertSame('G-DRG / ICD map', GdrgIcdMapResource::getNavigationLabel());
        $this->assertSame('Members master', MembersMasterResource::getNavigationLabel());
        $this->assertSame('NHIS medicines', NhisMedicineResource::getNavigationLabel());
        $this->assertSame('Provider credentialing', ProviderCredentialingResource::getNavigationLabel());
        $this->assertSame('Tariff books', TariffBookResource::getNavigationLabel());
        $this->assertSame('NHIS medicines', NhisMedicineResource::getPluralModelLabel());
    }

    public function test_settings_and_import_pages_are_grouped(): void
    {
        $this->assertSame(NavigationGroup::SETTINGS, ManageInsuranceSettings::getNavigationGroup());
        $this->assertSame(NavigationGroup::ADMINISTRATION, NhiaFeedbackImport::getNavigationGroup());
    }
}
