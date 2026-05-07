<?php

namespace Modules\Insurance\Services\Connectors\Nhis;

use Modules\Insurance\Models\InsuranceClaim;

class NhisXmlEncoder
{
    public function encodeClaim(InsuranceClaim|object $claim): string
    {
        if (method_exists($claim, 'loadMissing')) {
            $claim->loadMissing(['patient', 'lines']);
        }

        $xml = new \SimpleXMLElement('<Claims/>');
        $claimNode = $xml->addChild('Claim');
        $claimNode->addChild('ClaimIdentificationNumber', htmlspecialchars((string) $claim->claim_number));
        $claimNode->addChild('MemberNumber', htmlspecialchars((string) optional($claim->policy)->member_number));
        $claimNode->addChild('PatientId', htmlspecialchars((string) $claim->patient_id));
        $claimNode->addChild('TotalCost', number_format((float) $claim->total_billed_amount, 2, '.', ''));

        $treatments = $claimNode->addChild('Treatments');
        foreach ($claim->lines as $line) {
            $lineNode = $treatments->addChild('Treatment');
            $lineNode->addChild('ItemCode', htmlspecialchars((string) ($line->external_item_code ?? 'UNKNOWN')));
            $lineNode->addChild('Description', htmlspecialchars((string) $line->description));
            $lineNode->addChild('Quantity', (string) $line->quantity);
            $lineNode->addChild('TotalCost', number_format((float) $line->billed_amount, 2, '.', ''));
        }

        return $xml->asXML() ?: '';
    }
}
