<?php

namespace Modules\Insurance\Filament\Clusters\Insurance\Pages;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Modules\Insurance\Filament\Clusters\Insurance\InsuranceCluster;
use Modules\Insurance\Services\NhisFeedbackImportService;

/**
 * @property-read Schema $form
 */
class NhiaFeedbackImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = InsuranceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import NHIA Feedback';

    protected static ?string $title = 'Import NHIA Feedback';

    protected static ?int $navigationSort = 20;

    protected string $view = 'insurance::filament.pages.nhia-feedback-import';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array{created: int, updated: int, skipped: int, errors: array<int, string>}|null */
    public ?array $result = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('feedback_file')
                    ->label('NHIA Feedback XML')
                    ->helperText('Upload the response XML returned by NHIA after a batch submission.')
                    ->disk('local')
                    ->directory('insurance/feedback-imports')
                    ->acceptedFileTypes(['text/xml', 'application/xml', 'application/x-xml'])
                    ->storeFileNamesIn('original_filename')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function import(NhisFeedbackImportService $service): void
    {
        $path = data_get($this->data, 'feedback_file');

        if (! is_string($path) || $path === '') {
            Notification::make()->title('No file uploaded')->warning()->send();

            return;
        }

        try {
            $result = $service->import(Storage::disk('local')->path($path));
        } catch (\InvalidArgumentException $exception) {
            Notification::make()->title('Import failed')->body($exception->getMessage())->danger()->send();

            return;
        } finally {
            Storage::disk('local')->delete($path);
            $this->data = [];
            $this->form->fill([]);
        }

        $this->result = [
            'created' => $result->created,
            'updated' => $result->updated,
            'skipped' => $result->skipped,
            'errors' => $result->errors,
        ];

        Notification::make()
            ->title("Imported {$result->created} feedback record(s)")
            ->body("{$result->skipped} skipped. {$result->updated} updated.")
            ->success()
            ->send();
    }
}
