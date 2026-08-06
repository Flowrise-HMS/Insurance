<?php

namespace Modules\Insurance\Tests\Unit;

use Modules\Core\Enums\NavigationGroup;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\GdrgIcdMapResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\MembersMasterResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\NhisMedicineResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\ProviderCredentialingResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\MasterData\TariffBookResource\RelationManagers\TariffItemsRelationManager;
use Tests\TestCase;

class MasterDataResourcesTest extends TestCase
{
    public function test_master_data_resources_register_pages(): void
    {
        $resources = [
            NhisMedicineResource::class,
            MembersMasterResource::class,
            TariffBookResource::class,
            GdrgIcdMapResource::class,
            ProviderCredentialingResource::class,
        ];

        foreach ($resources as $resource) {
            $pages = $resource::getPages();
            $this->assertArrayHasKey('index', $pages, "{$resource} must register an index page");
            $this->assertArrayHasKey('create', $pages, "{$resource} must register a create page");
            $this->assertArrayHasKey('edit', $pages, "{$resource} must register an edit page");
        }
    }

    public function test_master_data_resources_use_infrastructure_navigation_group(): void
    {
        foreach ([
            NhisMedicineResource::class,
            MembersMasterResource::class,
            TariffBookResource::class,
            GdrgIcdMapResource::class,
            ProviderCredentialingResource::class,
        ] as $resource) {
            $this->assertSame(
                NavigationGroup::INFRASTRUCTURE,
                $resource::getNavigationGroup(),
                "{$resource} must belong to the infrastructure navigation group"
            );
        }
    }

    public function test_tariff_book_resource_registers_items_relation_manager(): void
    {
        $this->assertContains(TariffItemsRelationManager::class, TariffBookResource::getRelations());
    }
}
