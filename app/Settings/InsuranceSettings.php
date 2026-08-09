<?php

namespace Modules\Insurance\Settings;

use Spatie\LaravelSettings\Settings;

class InsuranceSettings extends Settings
{
    public bool $module_enabled = true;

    public bool $nhis_enabled = true;

    public bool $private_insurance_enabled = false;

    public bool $pricing_enabled = true;

    public bool $catalog_sync_enabled = true;

    public ?string $provider_accreditation_number = null;

    public ?string $eclaim_authorization_number = null;

    public ?string $default_speciality_code = null;

    /** @var array<string, string> */
    public array $master_table_versions = [
        'XMLFormatVersion' => '1',
        'MedicineVersion' => '1',
        'GDRGVersion' => '1',
        'TariffVersion' => '1',
        'ICDVersion' => '1',
        'OpenHDDVersion' => '1',
    ];

    public bool $require_claim_check_code = false;

    public int $prescribing_level = 1;

    public bool $enable_prescribing_level_warning = true;

    public string $member_verification_mode = 'offline';

    public bool $verify_members_on_encounter = false;

    public static function group(): string
    {
        return 'insurance';
    }
}
