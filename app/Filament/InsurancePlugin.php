<?php

namespace Modules\Insurance\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class InsurancePlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Insurance';
    }

    public function getId(): string
    {
        return 'insurance';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }
}
