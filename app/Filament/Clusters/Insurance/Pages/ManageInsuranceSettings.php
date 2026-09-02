<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
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
use Modules\Insurance\Enums\NhisPrescribingLevel;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Services\Otac\OtacClient;
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
            'otac_enabled' => $settings->otac_enabled,
            'otac_username' => $settings->otac_username,
            'otac_password' => $settings->otac_password,
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testOtacConnection')
                ->label('Test OTAC Connection')
                ->icon(Heroicon::OutlinedSignal)
                ->disabled(fn (): bool => ! app(OtacClient::class)->isConfigured())
                ->action(function (): void {
                    $client = app(OtacClient::class);

                    if ($client->login(force: true) === null) {
                        Notification::make()
                            ->title('OTAC login failed')
                            ->body('NHIA rejected the saved credentials. Check the username and password, then save and retry.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $info = $client->hpInfo();

                    if (! $info['ok']) {
                        Notification::make()
                            ->title('OTAC connection failed')
                            ->body("Logged in, but the facility lookup failed (HTTP {$info['status']}).")
                            ->danger()
                            ->send();

                        return;
                    }

                    $facility = trim(sprintf(
                        '%s (%s)',
                        data_get($info['body'], 'hpName', 'Unknown facility'),
                        data_get($info['body'], 'hpn', '—'),
                    ));

                    Notification::make()
                        ->title('OTAC connection OK')
                        ->body("Connected as {$facility}.")
                        ->success()
                        ->send();
                }),
        ];
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
                            ->options(collect(NhisPrescribingLevel::cases())
                                ->mapWithKeys(fn (NhisPrescribingLevel $level): array => [
                                    $level->ordinal() => (string) $level->getLabel(),
                                ])
                                ->all())
                            ->helperText('Facility default NHIS prescribing level (A–SM). Credentialing may override per prescriber.'),
                        Toggle::make('enable_prescribing_level_warning')
                            ->label('Warn on prescribing level breaches'),
                        Select::make('member_verification_mode')
                            ->label('Member Verification Mode')
                            ->options(['offline' => 'Offline (Members Master Table)', 'disabled' => 'Disabled'])
                            ->helperText('Verification is offline against the imported Members Master Table.'),
                    ]),
                Section::make('NHIA OTAC — Claim Check Codes')
                    ->description('Automatically generate NHIS claim check codes and verify membership via the NHIA OTAC API when an NHIS encounter is created. Save settings before using "Test OTAC Connection".')
                    ->schema([
                        Toggle::make('otac_enabled')
                            ->label('Enable OTAC claim code generation')
                            ->helperText('Each generated code logs a real attendance at NHIA — keep this off outside production.'),
                        TextInput::make('otac_username')
                            ->label('OTAC Username')
                            ->tel()
                            ->placeholder('e.g. 0245426972')
                            ->helperText('The phone number used to log in at otac.nhia.gov.gh.'),
                        TextInput::make('otac_password')
                            ->label('OTAC Password')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted. Must be re-entered if the application key is rotated.'),
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
        $settings->otac_enabled = (bool) ($state['otac_enabled'] ?? false);
        $settings->otac_username = filled($state['otac_username'] ?? null) ? (string) $state['otac_username'] : null;
        $settings->otac_password = filled($state['otac_password'] ?? null) ? (string) $state['otac_password'] : null;
        $settings->save();

        Notification::make()->title('Insurance settings saved')->success()->send();
    }
}
