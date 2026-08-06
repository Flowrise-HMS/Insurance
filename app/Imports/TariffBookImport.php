<?php

namespace Modules\Insurance\Imports;

use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffBook;
use Modules\Insurance\Models\TariffItem;

class TariffBookImport extends CsvMasterDataImporter
{
    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $options
     * @return array{created?: bool, error?: string}
     */
    protected function upsert(array $row, array $options): array
    {
        $code = trim((string) ($row['code'] ?? ''));

        if ($code === '') {
            return ['error' => 'Missing tariff code.'];
        }

        $payerCode = (string) ($options['payer'] ?? 'nhis');
        $payer = Payer::query()
            ->where('code', $payerCode)
            ->first();

        if (! $payer) {
            return ['error' => "Payer [{$payerCode}] not found."];
        }

        $book = $this->resolveBook($options);

        if (! $book) {
            return ['error' => 'Tariff book ['.($options['book_code'] ?? '').'] not found; create it first.'];
        }

        $attributes = [
            'payer_id' => $payer->id,
            'tariff_book_id' => $book->id,
            'item_type' => in_array(strtolower($row['item_type'] ?? ''), ['medication', 'service', 'consultation', 'procedure', 'lab_test'], true)
                ? strtolower($row['item_type'])
                : 'service',
            'name' => trim((string) ($row['name'] ?? '')),
            'price' => (string) max(0, (float) ($row['price'] ?? 0)),
            'currency' => strtoupper(substr((string) ($row['currency'] ?? 'GHS'), 0, 3)),
            'effective_from' => $this->nullable($row['effective_from'] ?? ''),
            'effective_to' => $this->nullable($row['effective_to'] ?? ''),
            'admission_type' => $this->nullable(strtoupper($row['admission_type'] ?? '')),
            'is_active' => in_array(strtolower($row['is_active'] ?? '1'), ['1', 'true', 'yes', 'active'], true),
            'source_file' => $options['source_file'] ?? null,
            'source_updated_at' => now(),
        ];

        if ($attributes['name'] === '') {
            return ['error' => "Tariff [{$code}] missing name."];
        }

        $item = TariffItem::query()->updateOrCreate(
            ['payer_id' => $payer->id, 'item_type' => $attributes['item_type'], 'external_code' => $code],
            $attributes
        );

        return ['created' => $item->wasRecentlyCreated];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveBook(array $options): ?TariffBook
    {
        $bookCode = (string) ($options['book_code'] ?? '');

        if ($bookCode === '') {
            return null;
        }

        $book = TariffBook::query()->where('code', $bookCode)->first();

        if (! $book && ($options['create_book'] ?? false)) {
            $book = TariffBook::query()->create([
                'code' => $bookCode,
                'name' => (string) ($options['book_name'] ?? $bookCode),
                'effective_from' => $options['book_effective_from'] ?? null,
                'effective_to' => $options['book_effective_to'] ?? null,
                'is_active' => true,
            ]);
        }

        return $book;
    }

    protected function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
