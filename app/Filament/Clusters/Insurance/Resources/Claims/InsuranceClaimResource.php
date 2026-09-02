<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\Claims;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\NavigationGroup;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
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

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = InsuranceCluster::class;

    protected static ?string $recordTitleAttribute = 'claim_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['claim_number', 'patient.mrn', 'patient.first_name', 'patient.middle_name', 'patient.last_name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Patient' => $record->patient?->full_name,
            'Status' => $record->status?->getLabel(),
            'Billed' => $record->currency !== null ? "{$record->currency} {$record->total_billed_amount}" : $record->total_billed_amount,
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('patient');
    }

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

    /**
     * @param  array<mixed>  $parameters
     */
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return ClaimBatchResource::getIndexUrl($parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public static function getFormSchemaForClaim(InsuranceClaim $claim): array
    {
        $registry = app(InsuranceSchemeRegistry::class);
        $scheme = $registry->forCode($claim->batch?->scheme_code ?? 'nhis');

        return $scheme->buildClaimFormSchema($claim);
    }
}
