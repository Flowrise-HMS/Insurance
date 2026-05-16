<?php

namespace Modules\Insurance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PayerType: string implements HasColor, HasDescription, HasLabel
{
    case NHIS = 'nhis';
    case PRIVATE = 'private';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::NHIS => 'NHIS',
            self::PRIVATE => 'Private Insurer',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NHIS => 'primary',
            self::PRIVATE => 'info',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::NHIS => 'National Health Insurance Scheme (Government).',
            self::PRIVATE => 'Commercial health insurance providers.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
