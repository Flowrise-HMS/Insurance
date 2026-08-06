<?php

namespace Modules\Insurance\DTOs;

final readonly class ValidationResult
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<int, array{code: ?string, message: string}>  $codedErrors
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
        public array $codedErrors = [],
    ) {}

    /**
     * @param  array<int, string>  $warnings
     */
    public static function pass(array $warnings = []): self
    {
        return new self(valid: true, warnings: $warnings);
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<int, array{code: ?string, message: string}>  $codedErrors
     */
    public static function fail(array $errors, array $warnings = [], array $codedErrors = []): self
    {
        return new self(valid: false, errors: $errors, warnings: $warnings, codedErrors: $codedErrors);
    }
}
