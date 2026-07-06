<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Settings\InsuranceSettings;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
use Modules\Insurance\Services\ClaimGenerationService;
use Modules\Insurance\Support\ClaimBatchCriteria;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Models\Medication;

/**
 * @property-read Schema $form
 */
class GenerateClaims extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ClaimBatchResource::class;

    protected static ?string $title = 'Generate NHIS Claims';

    protected static ?string $navigationLabel = 'Generate Claims';

    protected string $view = 'insurance::filament.pages.generate-claims';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        if (! app(InsuranceSettings::class)->nhis_enabled) {
            Notification::make()->title('NHIS is disabled in settings.')->danger()->send();
        }

        $this->form->fill([
            'scheme_code' => 'nhis',
            'year' => (int) now()->format('Y'),
            'month' => (int) now()->format('n'),
            'all' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('patient_id')
                    ->label('Patient')
                    ->options(fn () => Patient::query()->limit(100)->pluck('first_name', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('year')->numeric()->required(),
                TextInput::make('month')->numeric()->minValue(1)->maxValue(12)->required(),
                Select::make('service_id')
                    ->label('Service')
                    ->options(fn () => Service::query()->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('medication_id')
                    ->label('Medication')
                    ->options(fn () => Medication::query()->pluck('generic_name', 'id'))
                    ->searchable()
                    ->nullable(),
                Checkbox::make('all')->label('Include all eligible encounters'),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Claims Batch')
                ->action('generateClaims'),
        ];
    }

    public function generateClaims(ClaimGenerationService $generationService): void
    {
        $state = $this->form->getState();

        $criteria = new ClaimBatchCriteria(
            schemeCode: 'nhis',
            branchId: (string) $state['branch_id'],
            patientId: $state['patient_id'] ?? null,
            year: isset($state['year']) ? (int) $state['year'] : null,
            month: isset($state['month']) ? (int) $state['month'] : null,
            serviceId: $state['service_id'] ?? null,
            medicationId: $state['medication_id'] ?? null,
            all: (bool) ($state['all'] ?? false),
        );

        $batch = $generationService->generate($criteria);

        Notification::make()
            ->title("Generated {$batch->claims_count} claims")
            ->success()
            ->send();

        $this->redirect(ViewClaimBatch::getUrl(['record' => $batch]));
    }
}
