<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Branch;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\ApiResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

$modules = ['Core', 'Patient', 'Billing', 'Insurance'];

function insuranceApiPatient(Branch $branch): Patient
{
    return Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $branch->id]));
}

describe('payers', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'payers',
        'subject' => 'Payer',
        'scope' => 'insurance',
        'modules' => $modules,
        'branchIsolated' => false,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => Payer::factory()->create(['type' => PayerType::PRIVATE, ...$attributes]),
        'store' => fn (TestCase $test, Branch $branch): array => ['code' => 'acme-'.fake()->unique()->numerify('###'), 'name' => 'Acme Health', 'type' => 'private'],
        'invalid' => [['code' => 'nhis-2', 'name' => 'Second NHIS', 'type' => 'nhis'], 'type'],
        'update' => fn (TestCase $test, $record): array => ['name' => 'Acme Health Ltd'],
        'destroy' => true,
    ]);
});

describe('patient policies', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'patient-policies',
        'subject' => 'PatientPolicy',
        'scope' => 'insurance',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => PatientPolicy::factory()->create(['patient_id' => insuranceApiPatient($branch)->id, ...$attributes]),
        'store' => fn (TestCase $test, Branch $branch): array => [
            'patient_id' => insuranceApiPatient($branch)->id,
            'payer_id' => Payer::factory()->create(['type' => PayerType::PRIVATE])->id,
            'member_number' => 'MEM-123',
        ],
        'invalid' => [['member_number' => ''], 'member_number'],
        'update' => fn (TestCase $test, $record): array => ['plan_code' => 'GOLD'],
        'destroy' => true,
    ]);
});

describe('claims', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'claims',
        'subject' => 'InsuranceClaim',
        'scope' => 'insurance',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => InsuranceClaim::factory()->create(['patient_id' => insuranceApiPatient($branch)->id, ...$attributes]),
        'store' => fn (TestCase $test, Branch $branch): array => [
            'payer_id' => Payer::factory()->create(['type' => PayerType::PRIVATE])->id,
            'patient_id' => insuranceApiPatient($branch)->id,
            'lines' => [['description' => 'Consultation', 'quantity' => 1, 'billed_amount' => 150]],
        ],
        'invalid' => [['lines' => []], 'lines'],
    ]);
});

describe('claim batches', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'claim-batches',
        'subject' => 'ClaimBatch',
        'scope' => 'insurance',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => ClaimBatch::factory()->create(['branch_id' => $branch->id, ...$attributes]),
    ]);
});

describe('tariff items', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'tariff-items',
        'subject' => 'TariffItem',
        'scope' => 'insurance',
        'modules' => $modules,
        'branchIsolated' => false,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => TariffItem::factory()->create($attributes),
    ]);
});

describe('insurance workflow', function () use ($modules): void {
    beforeEach(function () use ($modules): void {
        $this->requireModule('Api');
        $this->migrateModules($modules);
        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

        foreach (['Payer', 'InsuranceClaim', 'PatientPolicy'] as $subject) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $action) {
                Permission::findOrCreate("{$action} {$subject}", 'web');
                $this->user->givePermissionTo("{$action} {$subject}");
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withToken($this->user->createToken('t', ['insurance:write'])->plainTextToken);
    });

    it('never exposes connector credentials and protects the NHIS payer', function (): void {
        $nhis = Payer::factory()->create(['type' => PayerType::NHIS, 'code' => 'nhis-x']);
        $private = Payer::factory()->create(['type' => PayerType::PRIVATE, 'connector_code' => 'http', 'connector_config' => ['base_url' => 'https://x.test', 'token' => 'secret']]);

        $response = $this->getJson("/api/v1/payers/{$private->id}")->assertOk();
        expect(json_encode($response->json()))->not->toContain('secret');

        // PayerPolicy refuses deleting the system NHIS payer.
        $this->deleteJson("/api/v1/payers/{$nhis->id}")->assertForbidden();
    });

    it('refuses NHIS claims and submits a private claim through its connector', function (): void {
        Queue::fake();
        $patient = insuranceApiPatient($this->branch);
        $line = [['description' => 'Consultation', 'quantity' => 1, 'billed_amount' => 150]];

        $nhis = Payer::factory()->create(['type' => PayerType::NHIS, 'code' => 'nhis-y']);
        $this->postJson('/api/v1/claims', ['payer_id' => $nhis->id, 'patient_id' => $patient->id, 'lines' => $line])->assertUnprocessable();

        $private = Payer::factory()->create(['type' => PayerType::PRIVATE, 'code' => 'private-generic-'.fake()->numerify('###')]);
        $id = $this->postJson('/api/v1/claims', ['payer_id' => $private->id, 'patient_id' => $patient->id, 'lines' => $line])
            ->assertCreated()
            ->assertJsonPath('data.lines.0.description', 'Consultation')
            ->json('data.id');

        $this->postJson("/api/v1/claims/{$id}/submit")->assertOk()->assertJsonPath('data.status', ClaimStatus::SUBMITTED->value);
    });

    it('keeps a single primary policy and verifies members', function (): void {
        $patient = insuranceApiPatient($this->branch);
        $payer = Payer::factory()->create(['type' => PayerType::PRIVATE]);
        $first = $this->postJson('/api/v1/patient-policies', ['patient_id' => $patient->id, 'payer_id' => $payer->id, 'member_number' => 'A1'])->json('data.id');
        $this->postJson('/api/v1/patient-policies', ['patient_id' => $patient->id, 'payer_id' => $payer->id, 'member_number' => 'B2'])->assertCreated();

        $this->getJson("/api/v1/patient-policies/{$first}")->assertJsonPath('data.is_primary', false);
        $this->postJson("/api/v1/patient-policies/{$first}/verify")->assertOk();
    });
});
