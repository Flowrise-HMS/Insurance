<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Pages;

use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Settings\InsuranceSettings;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;

/**
 * @property-read Schema $form
 */
class ManageInsuranceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = InsuranceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'NHIS Settings';

    protected static ?string $title = 'Insurance Settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'insurance::filament.pages.manage-insurance-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(InsuranceSettings $settings): void
    {
        $this->form->fill([
            'nhis_enabled' => $settings->nhis_enabled,
            'provider_accreditation_number' => $settings->provider_accreditation_number,
            'eclaim_authorization_number' => $settings->eclaim_authorization_number,
            'default_speciality_code' => $settings->default_speciality_code,
            'master_table_versions' => $settings->master_table_versions,
            'require_claim_check_code' => $settings->require_claim_check_code,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('provider_accreditation_number')
                    ->label('Provider Accreditation Number')
                    ->maxLength(64),
                TextInput::make('eclaim_authorization_number')
                    ->label('eClaim Authorization Number')
                    ->maxLength(128),
                TextInput::make('default_speciality_code')
                    ->label('Default Speciality Code')
                    ->maxLength(25),
                KeyValue::make('master_table_versions')
                    ->label('Master Table Versions')
                    ->keyLabel('Table')
                    ->valueLabel('Version'),
            ])
            ->statePath('data');
    }

    public function save(InsuranceSettings $settings): void
    {
        $state = $this->form->getState();

        $settings->provider_accreditation_number = $state['provider_accreditation_number'] ?? null;
        $settings->eclaim_authorization_number = $state['eclaim_authorization_number'] ?? null;
        $settings->default_speciality_code = $state['default_speciality_code'] ?? null;
        $settings->master_table_versions = $state['master_table_versions'] ?? $settings->master_table_versions;
        $settings->require_claim_check_code = (bool) ($state['require_claim_check_code'] ?? false);
        $settings->save();

        Notification::make()->title('Insurance settings saved')->success()->send();
    }
}
