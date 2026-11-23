<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\EditPayer;
use Modules\Insurance\Filament\Clusters\Insurance\Resources\Payers\Pages\ViewPayer;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Insurance');
    $this->migrateModules(['Core', 'Patient', 'Billing', 'Insurance']);
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    $this->payer = Payer::factory()->create(['code' => 'acme', 'type' => PayerType::PRIVATE]);
});

it('saves the HTTP connector settings from the payer form', function (): void {
    Livewire::test(EditPayer::class, ['record' => $this->payer->getRouteKey()])
        ->fillForm([
            'connector_code' => 'http',
            'connector_config' => [
                'base_url' => 'https://api.acme.test',
                'auth_type' => 'api_key',
                'api_key_header' => 'X-Key',
                'api_key' => 'k-123',
                'catalog_path' => '/tariffs',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $payer = $this->payer->fresh();

    expect($payer->connector_code)->toBe('http')
        ->and($payer->connector_config['base_url'])->toBe('https://api.acme.test')
        ->and($payer->connector_config['api_key'])->toBe('k-123');
});

it('tests the connection and syncs the catalog from the payer page', function (): void {
    $this->payer->update([
        'connector_code' => 'http',
        'connector_config' => ['base_url' => 'https://api.acme.test', 'auth_type' => 'none', 'catalog_path' => '/tariffs', 'health_path' => '/health'],
    ]);
    Http::fake([
        'api.acme.test/health' => Http::response(['ok' => true]),
        'api.acme.test/tariffs*' => Http::response(['items' => [['code' => 'X1', 'name' => 'X-ray', 'price' => 90]]]),
    ]);

    Livewire::test(ViewPayer::class, ['record' => $this->payer->getRouteKey()])
        ->callAction(TestAction::make('testConnection'))
        ->assertNotified('Payer API reachable')
        ->callAction(TestAction::make('syncCatalog'))
        ->assertNotified('Catalog synchronised');

    expect(TariffItem::query()->where('payer_id', $this->payer->id)->where('external_code', 'X1')->exists())->toBeTrue();
});

it('hides connector actions for payers without the HTTP connector', function (): void {
    Livewire::test(ViewPayer::class, ['record' => $this->payer->getRouteKey()])
        ->assertActionHidden('testConnection')
        ->assertActionHidden('syncCatalog');
});
