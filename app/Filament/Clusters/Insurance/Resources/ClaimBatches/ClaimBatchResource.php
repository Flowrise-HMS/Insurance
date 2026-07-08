<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages\GenerateClaims;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages\ListClaimBatches;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages\ViewClaimBatch;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\RelationManagers\ClaimsRelationManager;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Tables\ClaimBatchesTable;
use Modules\Insurance\Models\ClaimBatch;

class ClaimBatchResource extends Resource
{
    protected static ?string $model = ClaimBatch::class;

    protected static ?string $navigationLabel = 'Claims';

    protected static ?string $modelLabel = 'Claim Batch';

    protected static ?string $pluralModelLabel = 'Claim Batches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = InsuranceCluster::class;

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return ClaimBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ClaimsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClaimBatches::route('/'),
            'generate' => GenerateClaims::route('/generate'),
            'view' => ViewClaimBatch::route('/{record}'),
        ];
    }
}
