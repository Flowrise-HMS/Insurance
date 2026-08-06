<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Insurance\Database\Factories\ClaimBatchFactory;
use Modules\Insurance\Database\Factories\PayerFactory;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages\PreFlightReport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PreFlightReportPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Insurance']);
    }

    private function adminWithBatchAccess(): User
    {
        Permission::findOrCreate('ViewAny ClaimBatch', 'web');
        Permission::findOrCreate('View ClaimBatch', 'web');

        return User::factory()->create()
            ->givePermissionTo('ViewAny ClaimBatch', 'View ClaimBatch');
    }

    public function test_preflight_page_mounts_and_reports_valid_for_empty_batch(): void
    {
        $payer = PayerFactory::new()->create(['code' => 'nhis']);
        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-PREFLIGHT-001',
            'claims_count' => 0,
            'batch_amount' => 0,
        ]);

        Livewire::actingAs($this->adminWithBatchAccess())
            ->test(PreFlightReport::class, ['record' => $batch->id])
            ->assertOk()
            ->assertSet('batch.batch_number', 'NB-PREFLIGHT-001')
            ->assertSet('report.valid', true);
    }

    public function test_preflight_page_reports_errors_for_invalid_batch(): void
    {
        $payer = PayerFactory::new()->create(['code' => 'nhis']);
        $batch = ClaimBatchFactory::new()->create([
            'payer_id' => $payer->id,
            'batch_number' => 'NB-PREFLIGHT-ERR',
            'claims_count' => 1,
            'batch_amount' => 0,
        ]);

        Livewire::actingAs($this->adminWithBatchAccess())
            ->test(PreFlightReport::class, ['record' => $batch->id])
            ->assertOk()
            ->assertSet('report.valid', false);
    }
}
