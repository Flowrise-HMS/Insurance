<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\ClaimBatchResource;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages\ListClaimBatches;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages\ViewClaimBatch;
use Modules\Insurance\Models\ClaimBatch;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Insurance']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
});

FilamentResourceTestSuite::register([
    'resource' => ClaimBatchResource::class,
    'subject' => 'ClaimBatch',
    'model' => ClaimBatch::class,
    'listPage' => ListClaimBatches::class,
    'viewPage' => ViewClaimBatch::class,
    'searchColumn' => 'batch_number',
    'sortColumn' => 'batch_number',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): ClaimBatch => ClaimBatch::factory()->create([
        'branch_id' => $test->branch->id,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => ClaimBatch::factory()->count($count)->create([
        'branch_id' => $test->branch->id,
    ]),
]);
