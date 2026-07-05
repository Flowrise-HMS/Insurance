<?php

namespace Modules\Insurance\Schemes\Nhis;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Modules\Core\Settings\InsuranceSettings;
use Modules\Insurance\Contracts\InsuranceSchemeContract;
use Modules\Insurance\DTOs\ClaimGenerationResult;
use Modules\Insurance\DTOs\ExportedFile;
use Modules\Insurance\DTOs\ValidationResult;
use Modules\Insurance\Models\ClaimBatch;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Schemes\Nhis\NhisClaimAssembler;
use Modules\Insurance\Support\ClaimBatchCriteria;

class NhisSchemeHandler implements InsuranceSchemeContract
{
    public function __construct(
        protected InsuranceSettings $settings,
        protected NhisClaimAssembler $assembler,
        protected NhisClaimValidator $validator,
        protected NhisBatchExporter $exporter,
    ) {}

    public function code(): string
    {
        return 'nhis';
    }

    public function isEnabled(): bool
    {
        return $this->settings->module_enabled && $this->settings->nhis_enabled;
    }

    public function canUserManagePayer(): bool
    {
        return false;
    }

    public function generateClaims(ClaimBatchCriteria $criteria, ClaimBatch $batch): ClaimGenerationResult
    {
        return $this->assembler->generateClaimsForBatch($criteria, $batch);
    }

    public function buildClaimFormSchema(InsuranceClaim $claim): array
    {
        return [
            Section::make('NHIS Claim Header')->schema([
                TextInput::make('nhia_payload.claim_check_code')->label('Claim Check Code'),
                Select::make('nhia_payload.service_type')
                    ->label('Service Type')
                    ->options([
                        'OUT' => 'Outpatient',
                        'INP' => 'Inpatient',
                        'DIA' => 'Diagnostic',
                        'CAP' => 'Capitation',
                    ])
                    ->required(),
                Select::make('nhia_payload.pharmacy_included')
                    ->options(['YES' => 'Yes', 'NO' => 'No'])
                    ->required(),
                Select::make('nhia_payload.all_inclusive')
                    ->options(['YES' => 'Yes', 'NO' => 'No'])
                    ->required(),
                Select::make('nhia_payload.outcome_type')
                    ->label('Outcome Type')
                    ->options([
                        'ABS' => 'Absconded',
                        'DAA' => 'Discharged Against Medical Advice',
                        'DIE' => 'Died',
                        'DIS' => 'Discharged',
                        'TFR' => 'Transferred Out',
                    ])
                    ->required(),
                Select::make('nhia_payload.admission_type')
                    ->options([
                        'CRO' => 'Chronic Follow-up',
                        'EME' => 'Emergency',
                        'ACU' => 'Acute Episode',
                    ])
                    ->required(),
                TextInput::make('nhia_payload.speciality_code')->label('Speciality Code'),
                DatePicker::make('nhia_payload.admission_date')->label('Admission Date'),
                DatePicker::make('nhia_payload.discharge_date')->label('Discharge Date'),
                TextInput::make('nhia_payload.outpatient_code')->label('Outpatient Code'),
                TextInput::make('nhia_payload.outpatient_tariff_amount')->label('Outpatient Tariff Amount')->numeric(),
                TextInput::make('nhia_payload.referral_no')->label('Referral Number'),
                TextInput::make('nhia_payload.card_serial_number')->label('Card Serial Number'),
            ])->columns(2),
            Section::make('Treatments & Medicines')->schema([
                Repeater::make('lines')
                    ->relationship()
                    ->schema([
                        Select::make('line_type')
                            ->options([
                                'treatment' => 'Treatment',
                                'medicine' => 'Medicine',
                                'other' => 'Other',
                            ])
                            ->required(),
                        TextInput::make('external_item_code')->label('NHIA Code'),
                        TextInput::make('description')->required(),
                        TextInput::make('quantity')->numeric()->required(),
                        TextInput::make('billed_amount')->numeric()->required(),
                        TextInput::make('metadata.treatment_type')->label('Treatment Type'),
                        TextInput::make('metadata.icd_code')->label('ICD Code'),
                        TextInput::make('metadata.unit_price')->label('Unit Price')->numeric(),
                        DatePicker::make('metadata.medicine_date')->label('Medicine Date'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]),
        ];
    }

    public function validateClaim(InsuranceClaim $claim): ValidationResult
    {
        return $this->validator->validate($claim);
    }

    public function exportBatch(ClaimBatch $batch): ExportedFile
    {
        return $this->exporter->export($batch);
    }
}
