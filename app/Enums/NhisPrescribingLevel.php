<?php

namespace Modules\Insurance\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

enum NhisPrescribingLevel: string implements HasLabel
{
    case A = 'A';
    case M = 'M';
    case B1 = 'B1';
    case B2 = 'B2';
    case C = 'C';
    case D = 'D';
    case SM = 'SM';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::A => 'A — CHPS',
            self::M => 'M — Midwifery',
            self::B1 => 'B1 — Health Centre without Doctor',
            self::B2 => 'B2 — Health Centre with Doctor',
            self::C => 'C — District Hospital',
            self::D => 'D — Secondary/Tertiary Hospital',
            self::SM => 'SM — Specialist Medicines',
        };
    }

    public function ordinal(): int
    {
        return match ($this) {
            self::A => 1,
            self::M => 2,
            self::B1 => 3,
            self::B2 => 4,
            self::C => 5,
            self::D => 6,
            self::SM => 7,
        };
    }

    public static function tryFromCode(?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::tryFrom(strtoupper(trim($code)));
    }

    public static function fromCode(string $code): self
    {
        return self::tryFromCode($code)
            ?? throw new InvalidArgumentException("Unknown NHIS prescribing level [{$code}].");
    }

    public static function tryFromOrdinal(int $ordinal): ?self
    {
        return match ($ordinal) {
            1 => self::A,
            2 => self::M,
            3 => self::B1,
            4 => self::B2,
            5 => self::C,
            6 => self::D,
            7 => self::SM,
            default => null,
        };
    }

    /**
     * Resolve CSV/UI input that may be an official code (A/SM) or a legacy/ordinal integer.
     *
     * @return array{code: string, ordinal: int}|null
     */
    public static function resolve(?string $code, mixed $ordinalOrLegacy = null): ?array
    {
        $fromCode = self::tryFromCode($code);
        if ($fromCode) {
            return [
                'code' => $fromCode->value,
                'ordinal' => $fromCode->ordinal(),
            ];
        }

        if ($ordinalOrLegacy === null || $ordinalOrLegacy === '') {
            return null;
        }

        if (is_string($ordinalOrLegacy) && self::tryFromCode($ordinalOrLegacy)) {
            $level = self::fromCode($ordinalOrLegacy);

            return [
                'code' => $level->value,
                'ordinal' => $level->ordinal(),
            ];
        }

        $ordinal = (int) $ordinalOrLegacy;
        $fromOrdinal = self::tryFromOrdinal($ordinal);
        if ($fromOrdinal) {
            return [
                'code' => $fromOrdinal->value,
                'ordinal' => $fromOrdinal->ordinal(),
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = (string) $case->getLabel();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
