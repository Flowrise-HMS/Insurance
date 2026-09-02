<?php

namespace Modules\Insurance\DTOs;

final readonly class OtacAttendanceResult
{
    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * @param  array<string, mixed>  $attendanceData
     */
    public function __construct(
        public string $status,
        public ?string $ccc = null,
        public ?string $message = null,
        public array $attendanceData = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attendanceData
     */
    public static function generated(string $ccc, array $attendanceData = []): self
    {
        return new self(self::STATUS_GENERATED, $ccc, null, $attendanceData);
    }

    public static function failed(string $message): self
    {
        return new self(self::STATUS_FAILED, null, $message);
    }

    public static function skipped(string $message): self
    {
        return new self(self::STATUS_SKIPPED, null, $message);
    }

    /**
     * Cross-module payload — keeps Insurance types out of Clinical signatures.
     *
     * @return array{status: string, ccc: ?string, message: ?string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'ccc' => $this->ccc,
            'message' => $this->message,
        ];
    }
}
