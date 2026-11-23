<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Enums\NavigationGroup;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Actions\VerifyMemberAction;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\CreatePatientPolicy;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\EditPatientPolicy;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\ListPatientPolicies;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Pages\ViewPatientPolicy;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Schemas\PatientPolicyForm;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Tables\PatientPoliciesTable;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Services\MemberVerificationService;

/**
 * All patients' insurance policies in one place (also editable from the
 * patient form's insurance fields and the patient record).
 */
class PatientPolicyResource extends Resource
{
    protected static ?string $model = PatientPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = InsuranceCluster::class;

    protected static ?string $navigationLabel = 'Patient Policies';

    protected static ?string $modelLabel = 'patient policy';

    protected static ?string $pluralModelLabel = 'patient policies';

    protected static ?string $recordTitleAttribute = 'member_number';

    /**
     * Policies carry no branch; they follow their patient's branch scope.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('patient')
            ->with(['patient', 'payer']);
    }

    public static function form(Schema $schema): Schema
    {
        return PatientPolicyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Policy'))
                ->columns(3)
                ->schema([
                    TextEntry::make('patient.full_name')->label(__('Patient')),
                    TextEntry::make('patient.mrn')->label(__('MRN'))->placeholder('—'),
                    TextEntry::make('payer.name')->label(__('Payer')),
                    TextEntry::make('member_number')->label(__('Member number')),
                    TextEntry::make('plan_code')->label(__('Plan'))->placeholder('—'),
                    TextEntry::make('verification')
                        ->label(__('Verification'))
                        ->badge()
                        ->state(fn (PatientPolicy $record): string => app(MemberVerificationService::class)->badge($record)['label'])
                        ->color(fn (PatientPolicy $record): string => app(MemberVerificationService::class)->badge($record)['color']),
                    TextEntry::make('effective_from')->label(__('Effective from'))->date()->placeholder('—'),
                    TextEntry::make('effective_to')->label(__('Effective to'))->date()->placeholder('—'),
                    IconEntry::make('is_primary')->label(__('Primary'))->boolean(),
                    IconEntry::make('is_active')->label(__('Active'))->boolean(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(PatientPoliciesTable::columns())
            ->filters(PatientPoliciesTable::filters())
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                VerifyMemberAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatientPolicies::route('/'),
            'create' => CreatePatientPolicy::route('/create'),
            'view' => ViewPatientPolicy::route('/{record}'),
            'edit' => EditPatientPolicy::route('/{record}/edit'),
        ];
    }
}
