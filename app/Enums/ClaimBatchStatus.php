<?php

namespace Modules\Insurance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ClaimBatchStatus: string implements HasColor, HasLabel
{
    case GENERATED = 'generated';
    case UNDER_REVIEW = 'under_review';
    case VETTED = 'vetted';
    case EXPORTED = 'exported';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::GENERATED => 'Generated',
            self::UNDER_REVIEW => 'Under Review',
            self::VETTED => 'Vetted',
            self::EXPORTED => 'Exported',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GENERATED => 'gray',
            self::UNDER_REVIEW => 'warning',
            self::VETTED => 'info',
            self::EXPORTED => 'success',
        };
    }
}
