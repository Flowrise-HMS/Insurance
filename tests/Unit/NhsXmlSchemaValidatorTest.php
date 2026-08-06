<?php

namespace Modules\Insurance\Tests\Unit;

use Modules\Insurance\Schemes\Nhis\NhsXmlSchemaValidator;
use Tests\TestCase;

class NhsXmlSchemaValidatorTest extends TestCase
{
    public function test_valid_sample_xml_passes_schema(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Batch>
 <GeneralInformation>
  <VersionInformation>
   <XMLFormatVersion>1</XMLFormatVersion>
   <MedicineVersion>1</MedicineVersion>
   <GDRGVersion>1</GDRGVersion>
   <TariffVersion>1</TariffVersion>
   <ICDVersion>1</ICDVersion>
   <OpenHDDVersion>1</OpenHDDVersion>
  </VersionInformation>
  <BatchInformation>
   <BatchNumber>1</BatchNumber>
   <BatchAmount>75.50</BatchAmount>
   <BatchCurrency>GHC</BatchCurrency>
   <ClaimsCount>1</ClaimsCount>
   <CreationDate>28/07/2026</CreationDate>
   <ServiceYear>2026</ServiceYear>
   <ServiceMonth>05</ServiceMonth>
  </BatchInformation>
  <ProviderInformation>
   <ProviderAccreditationNumber>4563</ProviderAccreditationNumber>
   <eClaimAuthorizationNumber>12345567890</eClaimAuthorizationNumber>
  </ProviderInformation>
 </GeneralInformation>
 <Patients>
  <PatientData>
   <Surname>Doe</Surname>
   <OtherName>John</OtherName>
   <DateOfBirth>12/04/1990</DateOfBirth>
   <Infant>No</Infant>
   <MemberNumber>12345678</MemberNumber>
   <HospitalRecordNumber>MRN1</HospitalRecordNumber>
   <CardSerialNumber>UWJPL120A0093</CardSerialNumber>
   <Gender>M</Gender>
   <Claims>
    <Claim>
     <ClaimIdentificationNumber>C-1004523</ClaimIdentificationNumber>
     <ClaimCheckCode>4654351214657</ClaimCheckCode>
     <ServiceType>OUT</ServiceType>
     <PharmacyIncluded>YES</PharmacyIncluded>
     <AllInclusive>NO</AllInclusive>
     <OutcomeType>DIS</OutcomeType>
     <AdmissionType>ACU</AdmissionType>
     <SpecialityCode>ORTH</SpecialityCode>
     <AdmissionDate>14/05/2026</AdmissionDate>
     <OutPatientTariffAmount>20.00</OutPatientTariffAmount>
     <OutPatientCode>CONS01</OutPatientCode>
     <TotalCost>75.50</TotalCost>
     <TreatmentsCount>1</TreatmentsCount>
     <MedicinesCount>0</MedicinesCount>
     <Treatments>
      <Treatment>
       <Type>Diagnosis</Type>
       <TreatmentCode>CONS01</TreatmentCode>
       <ICDCode>J06.9</ICDCode>
       <Tariff>20.00</Tariff>
      </Treatment>
     </Treatments>
    </Claim>
   </Claims>
  </PatientData>
 </Patients>
</Batch>
XML;

        $result = app(NhsXmlSchemaValidator::class)->validate($xml);

        $this->assertTrue($result->valid, implode("\n", $result->errors));
    }

    public function test_invalid_admission_type_fails_schema(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Batch>
 <GeneralInformation>
  <VersionInformation>
   <XMLFormatVersion>1</XMLFormatVersion>
   <MedicineVersion>1</MedicineVersion>
   <GDRGVersion>1</GDRGVersion>
   <TariffVersion>1</TariffVersion>
   <ICDVersion>1</ICDVersion>
   <OpenHDDVersion>1</OpenHDDVersion>
  </VersionInformation>
  <BatchInformation>
   <BatchNumber>1</BatchNumber>
   <BatchAmount>20.00</BatchAmount>
   <BatchCurrency>GHC</BatchCurrency>
   <ClaimsCount>1</ClaimsCount>
   <CreationDate>28/07/2026</CreationDate>
   <ServiceYear>2026</ServiceYear>
   <ServiceMonth>05</ServiceMonth>
  </BatchInformation>
  <ProviderInformation>
   <ProviderAccreditationNumber>4563</ProviderAccreditationNumber>
   <eClaimAuthorizationNumber>12345567890</eClaimAuthorizationNumber>
  </ProviderInformation>
 </GeneralInformation>
 <Patients>
  <PatientData>
   <Surname>Doe</Surname>
   <OtherName>John</OtherName>
   <DateOfBirth>12/04/1990</DateOfBirth>
   <MemberNumber>12345678</MemberNumber>
   <CardSerialNumber>UWJPL120A0093</CardSerialNumber>
   <Gender>M</Gender>
   <Claims>
    <Claim>
     <ClaimIdentificationNumber>C-1</ClaimIdentificationNumber>
     <ServiceType>OUT</ServiceType>
     <PharmacyIncluded>NO</PharmacyIncluded>
     <AllInclusive>NO</AllInclusive>
     <OutcomeType>DIS</OutcomeType>
     <AdmissionType>XXX</AdmissionType>
     <SpecialityCode>ORTH</SpecialityCode>
     <AdmissionDate>14/05/2026</AdmissionDate>
     <TotalCost>20.00</TotalCost>
     <TreatmentsCount>0</TreatmentsCount>
     <MedicinesCount>0</MedicinesCount>
    </Claim>
   </Claims>
  </PatientData>
 </Patients>
</Batch>
XML;

        $result = app(NhsXmlSchemaValidator::class)->validate($xml);

        $this->assertFalse($result->valid);
    }
}
