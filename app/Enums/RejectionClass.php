<?php

namespace Modules\Insurance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum RejectionClass: string implements HasColor, HasDescription, HasLabel
{
    case TRANSPORT_REJECTED = 'transport_rejected';
    case SCHEMA_REJECTED = 'schema_rejected';
    case BUSINESS_REJECTED = 'business_rejected';
    case PAYER_REJECTED = 'payer_rejected';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::TRANSPORT_REJECTED => 'Transport Error',
            self::SCHEMA_REJECTED => 'Format Error',
            self::BUSINESS_REJECTED => 'Business Rule Error',
            self::PAYER_REJECTED => 'Payer Rejection',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TRANSPORT_REJECTED => 'danger',
            self::SCHEMA_REJECTED => 'warning',
            self::BUSINESS_REJECTED => 'warning',
            self::PAYER_REJECTED => 'danger',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::TRANSPORT_REJECTED => 'Technical failure during submission (e.g. timeout, 500 error).',
            self::SCHEMA_REJECTED => 'Submission format was invalid (e.g. invalid XML).',
            self::BUSINESS_REJECTED => 'Payer rejected due to data issues (e.g. invalid member ID).',
            self::PAYER_REJECTED => 'Claim was adjudicated and rejected by the payer.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
