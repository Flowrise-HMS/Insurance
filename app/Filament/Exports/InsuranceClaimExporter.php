<?php

namespace Modules\Insurance\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Insurance\Models\InsuranceClaim;

class InsuranceClaimExporter extends Exporter
{
    protected static ?string $model = InsuranceClaim::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('claim_number'),
            ExportColumn::make('batch.batch_number'),
            ExportColumn::make('payer.name'),
            ExportColumn::make('patient.mrn'),
            ExportColumn::make('invoice.invoice_number'),
            ExportColumn::make('status'),
            ExportColumn::make('total_billed_amount'),
            ExportColumn::make('total_approved_amount'),
            ExportColumn::make('total_rejected_amount'),
            ExportColumn::make('currency'),
            ExportColumn::make('submitted_at'),
            ExportColumn::make('reviewed_at'),
            ExportColumn::make('reconciled_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your insurance claim export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
