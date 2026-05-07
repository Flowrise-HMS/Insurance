<?php

namespace Modules\Insurance\Tests\Unit;

use Modules\Insurance\Services\Connectors\Nhis\NhisFeedbackParser;
use Modules\Insurance\Services\Connectors\Nhis\NhisXmlEncoder;
use stdClass;
use Tests\TestCase;

class NhisXmlIntegrationTest extends TestCase
{
    public function test_nhis_encoder_generates_xml_payload(): void
    {
        $claim = new stdClass;
        $claim->claim_number = 'CLM-XML-001';
        $claim->policy = (object) ['member_number' => 'MEM-1'];
        $claim->patient_id = 'PAT-1';
        $claim->total_billed_amount = '100.00';
        $claim->lines = collect([
            (object) [
                'external_item_code' => 'MED-1',
                'description' => 'Test Medication',
                'quantity' => 1,
                'billed_amount' => '100.00',
            ],
        ]);
        $claim->loadMissing = fn () => $claim;

        $encoder = new NhisXmlEncoder;
        $xml = $encoder->encodeClaim($claim);

        $this->assertStringContainsString('<ClaimIdentificationNumber>CLM-XML-001</ClaimIdentificationNumber>', $xml);
        $this->assertStringContainsString('<MemberNumber>MEM-1</MemberNumber>', $xml);
        $this->assertStringContainsString('<ItemCode>MED-1</ItemCode>', $xml);
    }

    public function test_nhis_feedback_parser_maps_rejection_class(): void
    {
        $parser = new NhisFeedbackParser;
        $parsed = $parser->parse('<Feedback><ClaimIdentificationNumber>CLM-1</ClaimIdentificationNumber><ClaimStatus>rejected</ClaimStatus><ErrorCode>301</ErrorCode><ErrorDescription>Not covered</ErrorDescription></Feedback>');

        $this->assertSame('CLM-1', $parsed['external_reference']);
        $this->assertSame('rejected', $parsed['decision_status']);
        $this->assertSame('business_rejected', $parsed['rejection_class']);
        $this->assertSame('301', $parsed['rejection_code']);
    }
}
