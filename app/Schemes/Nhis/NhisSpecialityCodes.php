<?php

namespace Modules\Insurance\Schemes\Nhis;

final class NhisSpecialityCodes
{
    /**
     * @var list<string>
     */
    public const ALLOW_LIST = [
        'ASUR',
        'DENT',
        'ENTH',
        'INVE',
        'MEDI',
        'OBGY',
        'OPDC',
        'OPHT',
        'ORTH',
        'PAED',
        'PSUR',
        'RSUR',
        'ZOOM',
    ];

    /**
     * @var list<string>
     */
    public const ADMISSION_TYPES = [
        'CRO',
        'EME',
        'ACU',
    ];

    public static function isValid(string $code): bool
    {
        return in_array(strtoupper($code), self::ALLOW_LIST, true);
    }

    public static function isValidAdmissionType(string $type): bool
    {
        return in_array(strtoupper($type), self::ADMISSION_TYPES, true);
    }
}
