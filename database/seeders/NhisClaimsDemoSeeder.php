<?php

namespace Modules\Insurance\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Core\Models\Unit;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Models\Payer;
use Modules\Insurance\Models\TariffItem;
use Modules\Insurance\Settings\InsuranceSettings;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;

/**
 * Seeds realistic NHIS demo data following the same domain path as the UI:
 *
 * Patient + NHIS policy → Clinical encounter (finished, NHIS coverage)
 * → Service requests / diagnoses / pharmacy dispenses → Billing invoice
 * → (user) Insurance → Generate Claims → review → export XML
 */
class NhisClaimsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InsuranceDatabaseSeeder::class);

        $branch = Branch::withoutGlobalScopes()->where('is_default', true)->first()
            ?? Branch::withoutGlobalScopes()->first();

        if (! $branch) {
            $this->command?->warn('No branch found. Run CoreDatabaseSeeder first.');

            return;
        }

        $organization = Organization::first();
        $actor = User::query()->where('email', 'superadmin@hms.com')->first()
            ?? User::query()->first();

        if (! $organization || ! $actor) {
            $this->command?->warn('Organization or user missing. Run DatabaseSeeder first.');

            return;
        }

        $this->configureInsuranceSettings();
        $catalog = $this->ensureNhisCatalog();
        $department = Department::query()->where('code', 'DEPT-GP')->first()
            ?? Department::query()->where('is_active', true)->first();

        $visitDate = now()->subDays(3)->startOfDay();

        $this->seedPatientVisit(
            branch: $branch,
            organization: $organization,
            actor: $actor,
            department: $department,
            catalog: $catalog,
            mrn: 'DEMO-NHIS-001',
            firstName: 'John',
            lastName: 'Doe',
            gender: Gender::MALE,
            dateOfBirth: Carbon::parse('1990-04-12'),
            memberNumber: '59340265',
            encounterNumber: 'DEMO-ENC-001',
            chiefComplaint: 'Cough, sore throat and fever for 2 days',
            visitDate: $visitDate,
            diagnosis: ['code' => 'J06.9', 'description' => 'Acute upper respiratory infection, unspecified'],
            services: [
                ['key' => 'consultation', 'quantity' => 1],
                ['key' => 'malaria_rdt', 'quantity' => 1],
            ],
            medicines: [
                ['key' => 'paracetamol', 'quantity' => 20],
            ],
        );

        $this->seedPatientVisit(
            branch: $branch,
            organization: $organization,
            actor: $actor,
            department: $department,
            catalog: $catalog,
            mrn: 'DEMO-NHIS-002',
            firstName: 'Ama',
            lastName: 'Mensah',
            gender: Gender::FEMALE,
            dateOfBirth: Carbon::parse('1985-08-20'),
            memberNumber: '28471936',
            encounterNumber: 'DEMO-ENC-002',
            chiefComplaint: 'Follow-up for hypertension',
            visitDate: $visitDate->copy()->addDay(),
            diagnosis: ['code' => 'I10', 'description' => 'Essential (primary) hypertension'],
            services: [
                ['key' => 'consultation', 'quantity' => 1],
            ],
            medicines: [],
        );

        $this->seedPatientVisit(
            branch: $branch,
            organization: $organization,
            actor: $actor,
            department: $department,
            catalog: $catalog,
            mrn: 'DEMO-NHIS-003',
            firstName: 'Kofi',
            lastName: 'Asante',
            gender: Gender::MALE,
            dateOfBirth: Carbon::parse('1978-01-15'),
            memberNumber: '71829354',
            encounterNumber: 'DEMO-ENC-003',
            chiefComplaint: 'Fever and body aches — malaria suspected',
            visitDate: $visitDate->copy()->addDays(2),
            diagnosis: ['code' => 'B54', 'description' => 'Unspecified malaria'],
            services: [
                ['key' => 'consultation', 'quantity' => 1],
                ['key' => 'fbc', 'quantity' => 1],
                ['key' => 'malaria_rdt', 'quantity' => 1],
            ],
            medicines: [
                ['key' => 'paracetamol', 'quantity' => 30],
            ],
        );

        $this->command?->info('NHIS claims demo data seeded.');
        $this->command?->info('Patients: DEMO-NHIS-001 (John Doe), DEMO-NHIS-002 (Ama Mensah), DEMO-NHIS-003 (Kofi Asante)');
        $this->command?->info('Next: Insurance → Claims → Generate Claims (branch Main, current year/month, Include all eligible).');
    }

    protected function configureInsuranceSettings(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->module_enabled = true;
        $settings->nhis_enabled = true;
        $settings->provider_accreditation_number = '4563';
        $settings->eclaim_authorization_number = '12345567890';
        $settings->default_speciality_code = 'GPEN';
        $settings->require_claim_check_code = false;
        $settings->save();
    }

    /**
     * @return array<string, array{service: Service, tariff: string, code: string, medication?: Medication}>
     */
    protected function ensureNhisCatalog(): array
    {
        $payer = Payer::query()->where('code', 'nhis')->firstOrFail();
        $tabletUnit = Unit::firstOrCreate(
            ['code' => 'tablet'],
            ['label' => 'Tablet', 'category' => 'count', 'is_fractional' => false, 'sort_order' => 10]
        );

        $definitions = [
            'consultation' => [
                'service_name' => 'General Consultation',
                'nhis_code' => 'CONS01',
                'tariff' => '20.00',
                'item_type' => 'service',
            ],
            'malaria_rdt' => [
                'service_name' => 'Malaria Test (RDT)',
                'nhis_code' => 'LAB01',
                'tariff' => '25.50',
                'item_type' => 'service',
            ],
            'fbc' => [
                'service_name' => 'Full Blood Count (FBC)',
                'nhis_code' => 'LAB02',
                'tariff' => '35.00',
                'item_type' => 'service',
            ],
            'paracetamol' => [
                'service_name' => 'Paracetamol 500mg Tablet',
                'nhis_code' => 'DRUG02',
                'tariff' => '1.50',
                'item_type' => 'medication',
            ],
        ];

        $catalog = [];

        foreach ($definitions as $key => $definition) {
            $service = Service::query()->where('name', $definition['service_name'])->first();

            if (! $service && $definition['item_type'] === 'medication') {
                $category = ServiceCategory::query()->where('code', 'PHA')->first();
                if ($category) {
                    $service = Service::query()->firstOrCreate(
                        [
                            'category_id' => $category->id,
                            'slug' => 'paracetamol-500mg-tablet',
                        ],
                        [
                            'name' => $definition['service_name'],
                            'description' => 'Demo NHIS medicine for claims walkthrough',
                            'price' => $definition['tariff'],
                            'requires_payment_before' => false,
                            'requires_prescription' => true,
                            'is_billable' => true,
                            'is_active' => true,
                            'metadata' => ['nhis_code' => $definition['nhis_code']],
                        ]
                    );
                }
            }

            if (! $service) {
                continue;
            }

            $service->update([
                'metadata' => array_merge($service->metadata ?? [], [
                    'nhis_code' => $definition['nhis_code'],
                ]),
            ]);

            TariffItem::query()->updateOrCreate(
                [
                    'payer_id' => $payer->id,
                    'item_type' => $definition['item_type'],
                    'external_code' => $definition['nhis_code'],
                ],
                [
                    'name' => $definition['service_name'],
                    'price' => $definition['tariff'],
                    'currency' => 'GHS',
                    'source_version' => 'demo-v1',
                    'is_active' => true,
                ]
            );

            $entry = [
                'service' => $service,
                'tariff' => $definition['tariff'],
                'code' => $definition['nhis_code'],
            ];

            if ($key === 'paracetamol') {
                $entry['medication'] = Medication::query()->updateOrCreate(
                    ['generic_name' => 'Paracetamol', 'strength' => '500mg'],
                    [
                        'service_id' => $service->id,
                        'brand_name' => 'Panadol',
                        'dosage_form' => DosageForm::TABLET,
                        'is_active' => true,
                        'stock_unit_id' => $tabletUnit->id,
                        'billing_unit_id' => $tabletUnit->id,
                        'dose_unit_id' => $tabletUnit->id,
                    ]
                );
            }

            $catalog[$key] = $entry;
        }

        DiagnosisCode::query()->firstOrCreate(
            ['code' => 'J06.9'],
            [
                'description' => 'Acute upper respiratory infection, unspecified',
                'category' => 'Respiratory',
                'nhis_covered' => true,
                'is_active' => true,
            ]
        );

        return $catalog;
    }

    /**
     * @param  array<string, array{service: Service, tariff: string, code: string, medication?: Medication}>  $catalog
     * @param  array{code: string, description: string}  $diagnosis
     * @param  array<int, array{key: string, quantity: int}>  $services
     * @param  array<int, array{key: string, quantity: int}>  $medicines
     */
    protected function seedPatientVisit(
        Branch $branch,
        Organization $organization,
        User $actor,
        ?Department $department,
        array $catalog,
        string $mrn,
        string $firstName,
        string $lastName,
        Gender $gender,
        Carbon $dateOfBirth,
        string $memberNumber,
        string $encounterNumber,
        string $chiefComplaint,
        Carbon $visitDate,
        array $diagnosis,
        array $services,
        array $medicines,
    ): void {
        $existing = Patient::withTrashed()->where('mrn', $mrn)->first();

        if ($existing && ! $existing->trashed()) {
            $encounter = $existing->encounters()->where('encounter_number', $encounterNumber)->first();
            $itemCount = $encounter
                ? RequestItem::query()->whereHas('serviceRequest', fn ($q) => $q->where('encounter_id', $encounter->id))->count()
                : 0;

            if ($itemCount > 0) {
                $this->command?->line("Skipping {$mrn} — already seeded.");

                return;
            }
        }

        Encounter::query()->where('encounter_number', $encounterNumber)->each(fn (Encounter $enc) => $this->purgeEncounter($enc));

        if ($existing) {
            if (! $existing->trashed()) {
                $this->command?->line("Repairing incomplete demo data for {$mrn}.");
            }
            ServiceRequest::query()->where('patient_id', $existing->id)->each(function (ServiceRequest $request) {
                RequestItem::query()->where('service_request_id', $request->id)->each(function (RequestItem $item) {
                    Dispense::query()->where('request_item_id', $item->id)->delete();
                    $item->delete();
                });
                $request->delete();
            });
            $existing->encounters()->each(function (Encounter $enc) {
                $this->purgeEncounter($enc);
            });
            PatientPolicy::query()->where('patient_id', $existing->id)->delete();
            $existing->forceDelete();
        }

        $payer = Payer::query()->where('code', 'nhis')->firstOrFail();

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'mrn' => $mrn,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            'nationality' => 'GH',
            'is_active' => true,
            'created_by' => $actor->id,
            'meta' => ['demo' => 'nhis_claims'],
        ]);

        PatientPolicy::query()->create([
            'payer_id' => $payer->id,
            'patient_id' => $patient->id,
            'member_number' => $memberNumber,
            'is_active' => true,
            'is_primary' => true,
            'effective_from' => now()->subYear(),
        ]);

        $encounter = Encounter::query()->create([
            'encounter_number' => $encounterNumber,
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'department_id' => $department?->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::FINISHED,
            'chief_complaint' => $chiefComplaint,
            'admitted_at' => $visitDate,
            'discharged_at' => $visitDate->copy()->addHours(2),
            'discharge_disposition' => DischargeDisposition::COMPLETED,
            'admitted_by' => $actor->id,
            'discharged_by' => $actor->id,
            'created_by' => $actor->id,
        ]);

        $diagnosisCode = DiagnosisCode::query()->firstOrCreate(
            ['code' => $diagnosis['code']],
            [
                'description' => $diagnosis['description'],
                'category' => 'Demo',
                'nhis_covered' => true,
                'is_active' => true,
            ]
        );

        EncounterDiagnosis::query()->create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'diagnosis_code_id' => $diagnosisCode->id,
            'icd_code' => $diagnosis['code'],
            'description' => $diagnosis['description'],
            'type' => 'primary',
            'ordered_by' => $actor->id,
            'is_active' => true,
        ]);

        $serviceRequest = ServiceRequest::query()->create([
            'request_number' => 'DEMO-SRQ-'.str_replace('DEMO-NHIS-', '', $mrn),
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'branch_id' => $branch->id,
            'status' => RequestStatus::ACTIVE,
            'priority' => RequestPriority::ROUTINE,
            'ordered_by' => $actor->id,
            'created_by' => $actor->id,
        ]);

        $invoiceSubtotal = '0.00';
        $invoiceLines = [];

        foreach ($services as $serviceSpec) {
            $catalogItem = $catalog[$serviceSpec['key']] ?? null;
            if (! $catalogItem) {
                continue;
            }

            $qty = max(1, (int) $serviceSpec['quantity']);
            $lineTotal = bcmul($catalogItem['tariff'], (string) $qty, 2);

            RequestItem::query()->create([
                'service_request_id' => $serviceRequest->id,
                'service_id' => $catalogItem['service']->id,
                'quantity' => $qty,
                'unit_price' => $catalogItem['tariff'],
                'discount_amount' => 0,
                'total_price' => $lineTotal,
                'status' => RequestItemStatus::COMPLETED,
                'fulfilled_by' => $actor->id,
                'fulfilled_at' => $visitDate,
            ]);

            $invoiceSubtotal = bcadd($invoiceSubtotal, $lineTotal, 2);
            $invoiceLines[] = [
                'service_id' => $catalogItem['service']->id,
                'description' => $catalogItem['service']->name,
                'quantity' => $qty,
                'unit_price' => $catalogItem['tariff'],
                'line_total' => $lineTotal,
                'insurance_expected_amount' => $lineTotal,
            ];
        }

        foreach ($medicines as $medicineSpec) {
            $catalogItem = $catalog[$medicineSpec['key']] ?? null;
            if (! $catalogItem || ! isset($catalogItem['medication'])) {
                continue;
            }

            $qty = max(1, (int) $medicineSpec['quantity']);
            $lineTotal = bcmul($catalogItem['tariff'], (string) $qty, 2);

            $requestItem = RequestItem::query()->create([
                'service_request_id' => $serviceRequest->id,
                'service_id' => $catalogItem['service']->id,
                'quantity' => $qty,
                'unit_price' => $catalogItem['tariff'],
                'discount_amount' => 0,
                'total_price' => $lineTotal,
                'status' => RequestItemStatus::COMPLETED,
                'fulfilled_by' => $actor->id,
                'fulfilled_at' => $visitDate,
            ]);

            Dispense::query()->create([
                'request_item_id' => $requestItem->id,
                'medication_id' => $catalogItem['medication']->id,
                'dispensed_by' => $actor->id,
                'quantity' => $qty,
                'dispensed_at' => $visitDate,
            ]);

            $invoiceSubtotal = bcadd($invoiceSubtotal, $lineTotal, 2);
            $invoiceLines[] = [
                'service_id' => $catalogItem['service']->id,
                'description' => $catalogItem['medication']->generic_name.' '.$catalogItem['medication']->strength,
                'quantity' => $qty,
                'unit_price' => $catalogItem['tariff'],
                'line_total' => $lineTotal,
                'insurance_expected_amount' => $lineTotal,
            ];
        }

        $invoice = Invoice::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'invoice_number' => 'INV-DEMO-'.str_replace('DEMO-NHIS-', '', $mrn),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Final,
            'currency' => 'GHS',
            'issued_at' => $visitDate,
            'due_at' => $visitDate->copy()->addDays(30),
            'subtotal' => $invoiceSubtotal,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => $invoiceSubtotal,
            'amount_paid' => 0,
            'encounter_discharged_at' => $encounter->discharged_at,
        ]);

        foreach ($invoiceLines as $line) {
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'service_id' => $line['service_id'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => 0,
                'insurance_expected_amount' => $line['insurance_expected_amount'],
                'metadata' => ['demo' => 'nhis_claims'],
            ]);
        }
    }

    protected function purgeEncounter(Encounter $enc): void
    {
        $enc->insuranceClaims()->each(function ($claim) {
            $claim->lines()->delete();
            $claim->delete();
        });

        RequestItem::query()->whereHas('serviceRequest', fn ($q) => $q->where('encounter_id', $enc->id))->each(function (RequestItem $item) {
            Dispense::query()->where('request_item_id', $item->id)->delete();
            $item->delete();
        });

        ServiceRequest::query()->where('encounter_id', $enc->id)->delete();
        EncounterDiagnosis::query()->where('encounter_id', $enc->id)->delete();

        Invoice::query()->where('encounter_id', $enc->id)->each(function (Invoice $invoice) {
            $invoice->lines()->delete();
            $invoice->delete();
        });

        $enc->delete();
    }
}
