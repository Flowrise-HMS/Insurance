<?php

namespace Modules\Insurance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\EditPayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\ListPayers;
use Modules\Insurance\Models\Payer;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The seeded NHIS payer is referenced by coverage, member verification and
 * claim generation; only private insurers may be deleted.
 */
class PayerResourceTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Payer $nhis;

    private Payer $private;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        foreach (['ViewAny Payer', 'View Payer', 'Update Payer', 'Delete Payer'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->admin->givePermissionTo($permission);
        }

        $this->nhis = Payer::factory()->create(['code' => 'nhis-test', 'type' => PayerType::NHIS, 'name' => 'NHIS']);
        $this->private = Payer::factory()->create(['code' => 'acme', 'type' => PayerType::PRIVATE, 'name' => 'Acme Insurance']);
    }

    public function test_delete_is_hidden_for_the_nhis_payer_but_available_for_private_insurers(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListPayers::class)
            ->assertTableActionHidden('delete', $this->nhis)
            ->assertTableActionVisible('delete', $this->private);

        Livewire::actingAs($this->admin)
            ->test(EditPayer::class, ['record' => $this->nhis->getRouteKey()])
            ->assertActionHidden('delete');
    }

    public function test_policy_and_model_refuse_deleting_the_nhis_payer(): void
    {
        $this->assertFalse($this->admin->can('delete', $this->nhis));
        $this->assertTrue($this->admin->can('delete', $this->private));

        $this->expectException(RuntimeException::class);
        $this->nhis->delete();
    }

    public function test_bulk_delete_skips_the_nhis_payer(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListPayers::class)
            ->callTableBulkAction('delete', [$this->nhis, $this->private]);

        $this->assertDatabaseHas('insurance_payers', ['id' => $this->nhis->id]);
        $this->assertDatabaseMissing('insurance_payers', ['id' => $this->private->id]);
    }

    public function test_nhis_payer_name_can_still_be_edited_but_not_its_code_or_type(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditPayer::class, ['record' => $this->nhis->getRouteKey()])
            ->fillForm(['name' => 'National Health Insurance Scheme'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->nhis->refresh();

        $this->assertSame('National Health Insurance Scheme', $this->nhis->name);
        $this->assertSame('nhis-test', $this->nhis->code);
        $this->assertSame(PayerType::NHIS, $this->nhis->type);
    }
}
