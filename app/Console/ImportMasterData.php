<?php

namespace Modules\Insurance\Console;

use Illuminate\Console\Command;
use Modules\Insurance\Contracts\MasterDataImporter;
use Modules\Insurance\Imports\AnnexCIcdGdrgImport;
use Modules\Insurance\Imports\CredentialingImport;
use Modules\Insurance\Imports\MedicineCatalogImport;
use Modules\Insurance\Imports\MembersMasterImport;
use Modules\Insurance\Imports\TariffBookImport;

class ImportMasterData extends Command
{
    protected $signature = 'insurance:import-master-data
                            {type : Import type: medicines|members|credentialing|annex-c|tariff}
                            {file : Path to the CSV file to import}
                            {--book-code= : Tariff book code (tariff import)}
                            {--book-name= : Tariff book name (tariff import)}
                            {--create-book : Create the tariff book if it does not exist (tariff import)}
                            {--payer=nhis : Payer code to attach tariff items to}';

    protected $description = 'Import NHIS master data (medicines, members, credentialing, Annex C, tariffs) from CSV.';

    /**
     * @var array<string, class-string<MasterDataImporter>>
     */
    protected array $importers = [
        'medicines' => MedicineCatalogImport::class,
        'members' => MembersMasterImport::class,
        'credentialing' => CredentialingImport::class,
        'annex-c' => AnnexCIcdGdrgImport::class,
        'tariff' => TariffBookImport::class,
    ];

    public function handle(): int
    {
        $type = $this->argument('type');
        $path = $this->argument('file');

        if (! isset($this->importers[$type])) {
            $this->error("Unknown import type [{$type}]. Supported: ".implode(', ', array_keys($this->importers)).'.');

            return self::FAILURE;
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File [{$path}] does not exist or is not readable.");

            return self::FAILURE;
        }

        /** @var MasterDataImporter $importer */
        $importer = app($this->importers[$type]);
        $result = $importer->import($path, [
            'source_file' => basename($path),
            'book_code' => $this->option('book-code'),
            'book_name' => $this->option('book-name'),
            'create_book' => (bool) $this->option('create-book'),
            'payer' => $this->option('payer'),
        ]);

        $this->info("Import complete: {$result->created} created, {$result->updated} updated, {$result->skipped} skipped.");

        foreach ($result->errors as $error) {
            $this->warn($error);
        }

        return $result->errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
