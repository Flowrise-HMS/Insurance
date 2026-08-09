<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Settings\InsuranceSettings;

/**
 * @property-read Schema $form
 */
class ManageInsuranceSettings extends Page implements HasForms
{
    use HasPageShield, InteractsWithForms;

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
            'prescribing_level' => $settings->prescribing_level,
            'enable_prescribing_level_warning' => $settings->enable_prescribing_level_warning,
            'member_verification_mode' => $settings->member_verification_mode,
            'verify_members_on_encounter' => $settings->verify_members_on_encounter,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('NHIS Claims')
                    ->description('Control NHIS claim processing behaviour')
                    ->schema([
                        Toggle::make('nhis_enabled')
                            ->label('Enable NHIS'),
                        Toggle::make('require_claim_check_code')
                            ->label('Require claim check code before export')
                            ->helperText('When enabled, NHIS claims must carry a valid claim check code (dial *842# option 1) before they can be marked ready and exported.'),
                        Select::make('prescribing_level')
                            ->label('Default Prescribing Level')
                            ->options([1 => 'Level 1', 2 => 'Level 2', 3 => 'Level 3'])
                            ->helperText('NHIA medicines prescribing level; providers may override via credentialing.'),
                        Toggle::make('enable_prescribing_level_warning')
                            ->label('Warn on prescribing level breaches'),
                        Select::make('member_verification_mode')
                            ->label('Member Verification Mode')
                            ->options(['offline' => 'Offline (Members Master Table)', 'disabled' => 'Disabled'])
                            ->helperText('Verification is offline against the imported Members Master Table.'),
                        Toggle::make('verify_members_on_encounter')
                            ->label('Verify members at encounter time'),
                    ]),
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
        $settings->nhis_enabled = (bool) ($state['nhis_enabled'] ?? false);
        $settings->require_claim_check_code = (bool) ($state['require_claim_check_code'] ?? false);
        $settings->prescribing_level = (int) ($state['prescribing_level'] ?? 1);
        $settings->enable_prescribing_level_warning = (bool) ($state['enable_prescribing_level_warning'] ?? true);
        $settings->member_verification_mode = (string) ($state['member_verification_mode'] ?? 'offline');
        $settings->verify_members_on_encounter = (bool) ($state['verify_members_on_encounter'] ?? false);
        $settings->save();

        Notification::make()->title('Insurance settings saved')->success()->send();
    }
}
