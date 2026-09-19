<?php

namespace Modules\Insurance\Filament\Clusters\Insurance;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Enums\SidebarGroup;

class InsuranceCluster extends Cluster
{
    protected static ?string $slug = 'insurance-cluster';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::Finance;

    protected static ?int $navigationSort = 20;
}
