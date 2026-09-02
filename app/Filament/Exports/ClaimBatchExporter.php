<?php

namespace Modules\Insurance\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Insurance\Models\ClaimBatch;

class ClaimBatchExporter extends Exporter
{
    protected static ?string $model = ClaimBatch::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('batch_number'),
            ExportColumn::make('scheme_code'),
            ExportColumn::make('payer.name'),
            ExportColumn::make('branch.name'),
            ExportColumn::make('service_year'),
            ExportColumn::make('service_month'),
            ExportColumn::make('status'),
            ExportColumn::make('claims_count'),
            ExportColumn::make('batch_amount'),
            ExportColumn::make('currency'),
            ExportColumn::make('exported_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your claim batch export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
