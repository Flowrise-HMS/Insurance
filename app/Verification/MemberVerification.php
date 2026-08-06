<?php

namespace Modules\Insurance\Verification;

use DateTimeInterface;

final readonly class MemberVerification
{
    public function __construct(
        public string $status,
        public ?string $errorCode = null,
        public ?DateTimeInterface $checkedAt = null,
        public ?string $source = null,
    ) {}

    public function verified(): bool
    {
        return $this->status === 'verified';
    }
}
