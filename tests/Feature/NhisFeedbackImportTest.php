<?php

namespace Modules\Insurance\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Insurance\Database\Factories\InsuranceClaimFactory;
use Modules\Insurance\Database\Factories\PayerFactory;
use Modules\Insurance\Enums\ClaimStatus;
use Modules\Insurance\Enums\RejectionClass;
use Modules\Insurance\Models\InsuranceClaimFeedback;
use Modules\Insurance\Services\NhisFeedbackImportService;
use Modules\Patient\Database\Factories\PatientFactory;
use Tests\TestCase;

class NhisFeedbackImportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Insurance']);

        $this->payer = PayerFactory::new()->create(['code' => 'nhis']);
    }

    public function test_import_rejects_claim_with_business_error_code(): void
    {
        $claim = $this->createClaim('CLM-IMP-001');
        $path = $this->writeFeedback(<<<'XML'
        <Feedback>
            <ClaimFeedback>
                <ClaimIdentificationNumber>CLM-IMP-001</ClaimIdentificationNumber>
                <ClaimStatus>REJECTED</ClaimStatus>
                <ErrorCode>301</ErrorCode>
                <ErrorDescription>Not covered</ErrorDescription>
            </ClaimFeedback>
        </Feedback>
        XML);

        $result = app(NhisFeedbackImportService::class)->import($path);

        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertSame([], $result->errors);

        $claim->refresh();
        $this->assertSame(ClaimStatus::REJECTED, $claim->status);

        $feedback = $claim->feedbacks()->first();
        $this->assertNotNull($feedback);
        $this->assertSame('rejected', $feedback->decision_status->value);
        $this->assertSame(RejectionClass::BUSINESS_REJECTED, $feedback->rejection_class);
        $this->assertSame('301', $feedback->rejection_code);
        $this->assertSame('Not covered', $feedback->rejection_reason);
    }

    public function test_import_approves_claim(): void
    {
        $claim = $this->createClaim('CLM-IMP-002');
        $path = $this->writeFeedback(<<<'XML'
        <Feedback>
            <ClaimFeedback>
                <ClaimIdentificationNumber>CLM-IMP-002</ClaimIdentificationNumber>
                <ClaimStatus>APPROVED</ClaimStatus>
            </ClaimFeedback>
        </Feedback>
        XML);

        $result = app(NhisFeedbackImportService::class)->import($path);

        $this->assertSame(1, $result->created);
        $claim->refresh();
        $this->assertSame(ClaimStatus::ACCEPTED, $claim->status);
        $this->assertSame('approved', $claim->feedbacks()->first()->decision_status->value);
    }

    public function test_import_maps_schema_and_business_error_prefixes(): void
    {
        $first = $this->createClaim('CLM-IMP-201');
        $second = $this->createClaim('CLM-IMP-301');
        $path = $this->writeFeedback(<<<'XML'
        <Feedback>
            <ClaimFeedback>
                <ClaimIdentificationNumber>CLM-IMP-201</ClaimIdentificationNumber>
                <ClaimStatus>REJECTED</ClaimStatus>
                <ErrorCode>201</ErrorCode>
                <ErrorDescription>Invalid member number</ErrorDescription>
            </ClaimFeedback>
            <ClaimFeedback>
                <ClaimIdentificationNumber>CLM-IMP-301</ClaimIdentificationNumber>
                <ClaimStatus>REJECTED</ClaimStatus>
                <ErrorCode>301</ErrorCode>
                <ErrorDescription>Missing serial</ErrorDescription>
            </ClaimFeedback>
        </Feedback>
        XML);

        $result = app(NhisFeedbackImportService::class)->import($path);

        $this->assertSame(2, $result->created);
        $this->assertSame(RejectionClass::SCHEMA_REJECTED, $first->feedbacks()->first()->rejection_class);
        $this->assertSame(RejectionClass::BUSINESS_REJECTED, $second->feedbacks()->first()->rejection_class);
    }

    public function test_import_skips_unknown_claim_and_duplicate_imports(): void
    {
        $claim = $this->createClaim('CLM-IMP-004');
        $path = $this->writeFeedback(<<<'XML'
        <Feedback>
            <ClaimFeedback>
                <ClaimIdentificationNumber>CLM-IMP-004</ClaimIdentificationNumber>
                <ClaimStatus>REJECTED</ClaimStatus>
                <ErrorCode>301</ErrorCode>
            </ClaimFeedback>
            <ClaimFeedback>
                <ClaimIdentificationNumber>CLM-UNKNOWN</ClaimIdentificationNumber>
                <ClaimStatus>REJECTED</ClaimStatus>
                <ErrorCode>301</ErrorCode>
            </ClaimFeedback>
        </Feedback>
        XML);

        $service = app(NhisFeedbackImportService::class);
        $result = $service->import($path);

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('CLM-UNKNOWN', $result->errors[0]);

        $this->assertSame(1, InsuranceClaimFeedback::query()->count());

        $reimport = $service->import($path);

        $this->assertSame(0, $reimport->created);
        $this->assertSame(2, $reimport->skipped);
        $this->assertSame(1, InsuranceClaimFeedback::query()->count());
    }

    public function test_import_throws_on_missing_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(NhisFeedbackImportService::class)->import(storage_path('app/testing/nhia-feedback/does-not-exist.xml'));
    }

    public function test_import_throws_on_invalid_xml(): void
    {
        $path = $this->writeFeedback('not xml at all');

        $this->expectException(\InvalidArgumentException::class);
        app(NhisFeedbackImportService::class)->import($path);
    }

    protected function createClaim(string $claimNumber)
    {
        $patient = PatientFactory::new()->create();

        return InsuranceClaimFactory::new()->create([
            'payer_id' => $this->payer->id,
            'policy_id' => null,
            'invoice_id' => null,
            'patient_id' => $patient->id,
            'claim_number' => $claimNumber,
            'status' => ClaimStatus::SUBMITTED,
            'total_billed_amount' => 50.00,
        ]);
    }

    protected function writeFeedback(string $xml): string
    {
        $directory = storage_path('app/testing/nhia-feedback');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory.'/nhia-feedback-'.uniqid().'.xml';
        file_put_contents($path, $xml);

        return $path;
    }
}
