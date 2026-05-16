<?php

namespace Modules\Insurance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ClaimDecisionStatus: string implements HasColor, HasDescription, HasLabel
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PARTIAL = 'partial';
    case REJECTED = 'rejected';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::PARTIAL => 'Partial',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::APPROVED => 'success',
            self::PARTIAL => 'warning',
            self::REJECTED => 'danger',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::PENDING => 'Payer has not yet decided on the claim.',
            self::APPROVED => 'Payer has approved the full amount.',
            self::PARTIAL => 'Payer has approved only part of the billed amount.',
            self::REJECTED => 'Payer has denied the claim.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
