<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Services\ClaimBatchService;
use Modules\Insurance\Validation\ClaimValidationEngine;

class PreFlightReport extends Page
{
    protected static string $resource = ClaimBatchResource::class;

    protected static ?string $title = 'Pre-flight Report';

    protected static ?string $navigationLabel = 'Pre-flight';

    protected string $view = 'insurance::filament.pages.pre-flight-report';

    public ?ClaimBatch $batch = null;

    /** @var array{valid: bool, errors: array<int, array{code: string, message: string, claim_number: ?string}>, warnings: array<int, array{code: string, message: string, claim_number: ?string}>} */
    public array $report = [
        'valid' => false,
        'errors' => [],
        'warnings' => [],
    ];

    public function mount(string $record): void
    {
        $this->batch = ClaimBatch::query()->findOrFail($record);
        $this->refreshReport();
    }

    public function refreshReport(): void
    {
        $this->report = app(ClaimValidationEngine::class)
            ->validateBatch($this->batch->fresh(['claims']))
            ->toArray();
    }

    public function runPreflight(): void
    {
        $this->refreshReport();

        $message = $this->report['valid']
            ? 'Batch passed pre-flight validation and is exportable.'
            : count($this->report['errors']).' blocking error(s) found.';

        Notification::make()->title($message)
            ->success($this->report['valid'])
            ->danger(! $this->report['valid'])
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preflight')
                ->label('Run Pre-flight')
                ->icon('heroicon-o-arrow-path')
                ->action('runPreflight'),
            Action::make('export')
                ->label('Export XML')
                ->requiresConfirmation()
                ->action(function (ClaimBatchService $service) {
                    try {
                        $exported = $service->export($this->batch->fresh(['claims']));
                    } catch (\InvalidArgumentException $exception) {
                        Notification::make()->title('Export blocked')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Batch exported successfully')->success()->send();

                    return Storage::disk('local')->download(
                        $exported->path,
                        $exported->filename,
                    );
                }),
        ];
    }
}
