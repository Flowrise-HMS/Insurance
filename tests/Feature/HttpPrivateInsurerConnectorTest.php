<?php

namespace Modules\Insurance\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\PayerType;
use Modules\Insurance\Jobs\PollInsuranceClaimFeedbackJob;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Insurance\Services\CatalogSyncService;
use Modules\Insurance\Services\ClaimReconciliationService;
use Modules\Insurance\Services\ClaimSubmissionService;
use Modules\Insurance\Services\Connectors\PrivateInsurer\GenericPrivateInsurerConnector;
use Modules\Insurance\Services\Connectors\PrivateInsurer\HttpPrivateInsurerConnector;
use Modules\Insurance\Services\PayerConnectorRegistry;
use Tests\TestCase;

class HttpPrivateInsurerConnectorTest extends TestCase
{
    use DatabaseTransactions;

    private Payer $payer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Billing', 'Insurance']);
        Http::preventStrayRequests();

        $this->payer = Payer::factory()->create([
            'code' => 'acme',
            'type' => PayerType::PRIVATE,
            'connector_code' => 'http',
            'connector_config' => [
                'base_url' => 'https://api.acme.test/v1',
                'auth_type' => 'bearer',
                'token' => 'secret-token',
                'catalog_path' => '/tariffs',
                'claim_path' => '/claims',
                'feedback_path' => '/claims/{reference}',
                'feedback_status_map' => ['PAID' => 'approved', 'IN_REVIEW' => 'pending'],
                'field_map' => ['catalog_items_key' => 'data.items', 'claim_reference_key' => 'claimId'],
            ],
        ]);
    }

    private function claim(): InsuranceClaim
    {
        $claim = InsuranceClaim::factory()->create([
            'payer_id' => $this->payer->id,
            'status' => ClaimStatus::DRAFT,
            'claim_number' => 'CLM-0001',
        ]);
        InsuranceClaimLine::factory()->create([
            'claim_id' => $claim->id,
            'external_item_code' => 'CONS01',
            'description' => 'Consultation',
            'quantity' => 1,
            'billed_amount' => 150,
        ]);

        return $claim;
    }

    public function test_registry_resolves_connectors_per_payer(): void
    {
        $registry = app(PayerConnectorRegistry::class);

        $this->assertInstanceOf(HttpPrivateInsurerConnector::class, $registry->forPayer($this->payer));
        $this->assertInstanceOf(GenericPrivateInsurerConnector::class, $registry->forPayer(Payer::factory()->create(['type' => PayerType::PRIVATE, 'connector_code' => null])));
        $this->assertInstanceOf(GenericPrivateInsurerConnector::class, $registry->forPayer(Payer::factory()->create(['code' => 'private-generic', 'type' => PayerType::PRIVATE])));

        $this->expectException(InvalidArgumentException::class);
        $registry->forPayer(Payer::factory()->create(['type' => PayerType::NHIS, 'code' => 'nhis-x']));
    }

    public function test_connector_settings_are_encrypted_at_rest(): void
    {
        $raw = \DB::table('insurance_payers')->where('id', $this->payer->id)->value('connector_config');

        $this->assertStringNotContainsString('secret-token', (string) $raw);
        $this->assertSame('secret-token', $this->payer->fresh()->connector_config['token']);
    }

    public function test_catalog_sync_upserts_tariff_items_and_records_the_run(): void
    {
        Http::fake([
            'api.acme.test/v1/tariffs*' => Http::response([
                'data' => ['items' => [
                    ['code' => 'CONS01', 'name' => 'Consultation', 'price' => '150.00', 'type' => 'service'],
                    ['code' => 'LAB01', 'name' => 'Full blood count', 'price' => 80],
                    ['code' => '', 'name' => 'ignored'],
                ]],
                'watermark' => '2026-11-23T00:00:00Z',
            ]),
        ]);

        $sync = app(CatalogSyncService::class)->sync($this->payer, 'tariff', '2026-11-01');

        $this->assertSame('success', $sync->status);
        $this->assertSame(2, $sync->records_processed);
        $this->assertSame('2026-11-23T00:00:00Z', $sync->watermark);
        $this->assertSame(2, TariffItem::query()->where('payer_id', $this->payer->id)->count());

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'since=2026-11-01')
            && $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_catalog_sync_failure_is_recorded(): void
    {
        Http::fake(['api.acme.test/*' => Http::response(['error' => 'down'], 503)]);

        try {
            app(CatalogSyncService::class)->sync($this->payer, 'tariff');
            $this->fail('Expected the sync to fail.');
        } catch (RequestException) {
            // expected
        }

        $this->assertDatabaseMissing('insurance_catalog_syncs', ['payer_id' => $this->payer->id, 'status' => 'success']);
    }

    public function test_claim_submission_posts_the_claim_and_stores_the_payer_reference(): void
    {
        Queue::fake();
        Http::fake(['api.acme.test/v1/claims' => Http::response(['claimId' => 'ACME-77', 'status' => 'received'], 201)]);
        $claim = $this->claim();

        $submission = app(ClaimSubmissionService::class)->submit($claim);

        $this->assertSame('http', $submission->connector_code);
        $this->assertSame('ACME-77', $submission->external_reference);
        $this->assertSame('received', $submission->submission_status);
        $this->assertSame(ClaimStatus::SUBMITTED, $claim->fresh()->status);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->hasHeader('Idempotency-Key')
                && $request['claim_number'] === 'CLM-0001'
                && $request['lines'][0]['code'] === 'CONS01';
        });
        Queue::assertPushed(PollInsuranceClaimFeedbackJob::class, fn ($job) => $job->externalReference === 'ACME-77');
    }

    public function test_refused_submission_leaves_the_claim_unsubmitted(): void
    {
        Queue::fake();
        Http::fake(['api.acme.test/v1/claims' => Http::response(['error' => 'invalid member'], 422)]);
        $claim = $this->claim();

        try {
            app(ClaimSubmissionService::class)->submit($claim);
            $this->fail('Expected the submission to fail.');
        } catch (RequestException) {
            // expected
        }

        $this->assertSame(ClaimStatus::DRAFT, $claim->fresh()->status);
        $this->assertDatabaseMissing('insurance_claim_submissions', ['claim_id' => $claim->id]);
        Queue::assertNothingPushed();
    }

    public function test_feedback_polling_applies_a_mapped_decision(): void
    {
        Http::fake(['api.acme.test/v1/claims/ACME-77' => Http::response(['status' => 'PAID'])]);
        $claim = $this->claim();
        $claim->update(['status' => ClaimStatus::SUBMITTED]);

        (new PollInsuranceClaimFeedbackJob($claim->id, 'ACME-77'))
            ->handle(app(PayerConnectorRegistry::class), app(ClaimReconciliationService::class));

        $this->assertSame(ClaimStatus::ACCEPTED, $claim->fresh()->status);
        $this->assertDatabaseHas('insurance_claim_feedbacks', ['claim_id' => $claim->id, 'decision_status' => 'approved']);
    }

    public function test_pending_feedback_releases_the_job_to_poll_again(): void
    {
        Http::fake(['api.acme.test/v1/claims/ACME-77' => Http::response(['status' => 'IN_REVIEW'])]);
        $claim = $this->claim();
        $claim->update(['status' => ClaimStatus::SUBMITTED]);

        $job = (new PollInsuranceClaimFeedbackJob($claim->id, 'ACME-77'))->withFakeQueueInteractions();
        $job->handle(app(PayerConnectorRegistry::class), app(ClaimReconciliationService::class));

        $job->assertReleased();
        $this->assertSame(ClaimStatus::SUBMITTED, $claim->fresh()->status);
    }

    public function test_test_connection_reports_reachability(): void
    {
        Http::fake([
            'api.acme.test/v1' => Http::response('ok'),
            'api.acme.test/v1/' => Http::response('ok'),
        ]);

        $this->assertTrue(app(HttpPrivateInsurerConnector::class)->testConnection($this->payer)['ok']);

        $unconfigured = Payer::factory()->create(['type' => PayerType::PRIVATE, 'connector_code' => 'http', 'connector_config' => []]);
        $this->assertFalse(app(HttpPrivateInsurerConnector::class)->testConnection($unconfigured)['ok']);
    }
}
