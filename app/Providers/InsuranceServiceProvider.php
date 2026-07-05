<?php

namespace Modules\Insurance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Contracts\InsurancePricingResolver;
use Modules\Core\Support\AppSettings;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\PatientPolicy;
use Modules\Insurance\Schemes\InsuranceSchemeRegistry;
use Modules\Insurance\Schemes\Nhis\NhisSchemeHandler;
use Modules\Insurance\Services\ClaimBatchService;
use Modules\Insurance\Services\ClaimGenerationService;
use Modules\Insurance\Services\DefaultInsurancePricingService;
use Modules\Insurance\Services\PatientInsuranceService;
use Modules\Patient\Models\Patient;
use Nwidart\Modules\Support\ModuleServiceProvider;

class InsuranceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Insurance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'insurance';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        if (! $this->insuranceModuleEnabled()) {
            return;
        }

        $this->app->bind(InsurancePricingResolver::class, DefaultInsurancePricingService::class);
        $this->app->singleton(PatientInsuranceService::class);
        $this->app->singleton(InsuranceSchemeRegistry::class, function ($app) {
            $registry = new InsuranceSchemeRegistry;
            $registry->register($app->make(NhisSchemeHandler::class));

            return $registry;
        });
        $this->app->singleton(ClaimGenerationService::class);
        $this->app->singleton(ClaimBatchService::class);
    }

    public function boot(): void
    {
        parent::boot();

        if (! $this->insuranceModuleEnabled()) {
            return;
        }

        Patient::resolveRelationUsing('insurancePolicies', function (Patient $patient) {
            return $patient->hasMany(PatientPolicy::class, 'patient_id');
        });

        InvoiceLine::resolveRelationUsing('insuranceClaimLines', function (InvoiceLine $line) {
            return $line->hasMany(InsuranceClaimLine::class, 'invoice_line_id');
        });

        Encounter::resolveRelationUsing('insuranceClaims', function (Encounter $encounter) {
            return $encounter->hasMany(InsuranceClaim::class, 'encounter_id');
        });
    }

    protected function insuranceModuleEnabled(): bool
    {
        if (! config('insurance.enabled', true)) {
            return false;
        }

        try {
            $settings = app(AppSettings::class);

            return $settings->insurance()->module_enabled && $settings->features()->insurance_enabled;
        } catch (\Throwable) {
            return config('insurance.enabled', true);
        }
    }
}
