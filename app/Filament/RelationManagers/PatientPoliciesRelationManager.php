<?php

namespace Modules\Insurance\Filament\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Actions\VerifyMemberAction;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Schemas\PatientPolicyForm;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\PatientPolicies\Tables\PatientPoliciesTable;
use Modules\Insurance\Models\PatientPolicy;

/**
 * A patient's insurance policies on the patient record. Policies can also be
 * maintained from the patient form's Insurance Information fields and the
 * Insurance → Patient Policies resource; all three share the same records.
 */
class PatientPoliciesRelationManager extends RelationManager
{
    protected static string $relationship = 'insurancePolicies';

    protected static ?string $title = 'Insurance Policies';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Insurance Policies');
    }

    /**
     * Editable on the patient View page too; Create/Update PatientPolicy
     * permissions still gate the actions.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PatientPolicyForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('payer'))
            ->columns(PatientPoliciesTable::columns(withPatient: false))
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->after(fn (PatientPolicy $record) => $record->demoteOtherPrimaries()),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (PatientPolicy $record) => $record->demoteOtherPrimaries()),
                VerifyMemberAction::make(),
            ])
            ->emptyStateHeading(__('No insurance policies'));
    }
}
