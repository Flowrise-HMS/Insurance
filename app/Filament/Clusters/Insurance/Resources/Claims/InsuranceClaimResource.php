<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Pages\EditInsuranceClaim;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims\Schemas\InsuranceClaimForm;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Schemes\InsuranceSchemeRegistry;

class InsuranceClaimResource extends Resource
{
    protected static ?string $model = InsuranceClaim::class;

    protected static ?string $navigationLabel = 'Claim Review';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $cluster = InsuranceCluster::class;

    public static function form(Schema $schema): Schema
    {
        return InsuranceClaimForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditInsuranceClaim::route('/{record}/edit'),
        ];
    }

    public static function getFormSchemaForClaim(InsuranceClaim $claim): array
    {
        $registry = app(InsuranceSchemeRegistry::class);
        $scheme = $registry->forCode($claim->batch?->scheme_code ?? 'nhis');

        return $scheme->buildClaimFormSchema($claim);
    }
}
