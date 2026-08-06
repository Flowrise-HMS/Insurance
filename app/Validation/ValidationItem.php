<?php

namespace Modules\Insurance\Validation;

final readonly class ValidationItem
{
    public function __construct(
        public string $code,
        public string $message,
        public string $severity = 'error',
        public ?string $claimNumber = null,
    ) {}

    public function isError(): bool
    {
        return $this->severity === 'error';
    }
}
