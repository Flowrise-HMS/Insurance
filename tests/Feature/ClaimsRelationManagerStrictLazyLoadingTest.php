<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\Pages\ViewClaimBatch;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\ClaimBatches\RelationManagers\ClaimsRelationManager;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Tests\TestCase;

/**
 * The "Claims in Batch" tab renders patient, visit and batch currency from
 * relations, so it must eager-load them to survive strict lazy loading.
 */
class ClaimsRelationManagerStrictLazyLoadingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules();
        Gate::before(fn () => true);
        Livewire::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_claims_in_batch_tab_renders_with_strict_lazy_loading(): void
    {
        $batch = ClaimBatch::factory()->create();
        InsuranceClaim::factory()->count(2)->create(['batch_id' => $batch->id]);

        Model::preventLazyLoading(true);

        Livewire::test(ClaimsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewClaimBatch::class,
        ])->assertOk();
    }
}
