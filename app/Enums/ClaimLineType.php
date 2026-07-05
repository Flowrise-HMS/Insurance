<?php

namespace Modules\Insurance\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ClaimLineType: string implements HasLabel
{
    case TREATMENT = 'treatment';
    case MEDICINE = 'medicine';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::TREATMENT => 'Treatment',
            self::MEDICINE => 'Medicine',
            self::OTHER => 'Other',
        };
    }
}
