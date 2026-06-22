<?php

namespace Modules\Insurance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Billing\Models\InvoiceLine;
use Modules\Core\Contracts\InsurancePricingResolver;
use Modules\Core\Support\AppSettings;
use Modules\Insurance\Models\InsuranceClaimLine;
use Modules\Insurance\Models\PatientPolicy;
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
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

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
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }

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
