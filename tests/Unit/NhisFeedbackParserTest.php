<?php

namespace Modules\Insurance\Tests\Unit;

use Modules\Insurance\Services\Connectors\Nhis\NhisFeedbackParser;
use Tests\TestCase;

class NhisFeedbackParserTest extends TestCase
{
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
