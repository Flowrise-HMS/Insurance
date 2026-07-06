<?php

namespace Modules\Insurance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ClaimStatus: string implements HasColor, HasDescription, HasLabel
{
    case DRAFT = 'draft';
    case VALIDATED = 'validated';
    case QUEUED = 'queued';
    case SUBMITTED = 'submitted';
    case ACCEPTED = 'accepted';
    case PARTIAL = 'partial';
    case REJECTED = 'rejected';
    case RECONCILED = 'reconciled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::VALIDATED => 'Validated',
            self::QUEUED => 'Queued',
            self::SUBMITTED => 'Submitted',
            self::ACCEPTED => 'Accepted',
            self::PARTIAL => 'Partially Approved',
            self::REJECTED => 'Rejected',
            self::RECONCILED => 'Reconciled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::VALIDATED => 'info',
            self::QUEUED => 'warning',
            self::SUBMITTED => 'primary',
            self::ACCEPTED => 'success',
            self::PARTIAL => 'warning',
            self::REJECTED => 'danger',
            self::RECONCILED => 'success',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::DRAFT => 'Claim is being prepared.',
            self::VALIDATED => 'Claim has passed local validation.',
            self::QUEUED => 'Claim is waiting for background submission.',
            self::SUBMITTED => 'Claim has been sent to the payer.',
            self::ACCEPTED => 'Claim has been fully approved by the payer.',
            self::PARTIAL => 'Claim has been partially approved.',
            self::REJECTED => 'Claim has been rejected by the payer.',
            self::RECONCILED => 'Claim feedback has been reconciled with billing.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canMarkReady(): bool
    {
        return $this === self::DRAFT;
    }
}
