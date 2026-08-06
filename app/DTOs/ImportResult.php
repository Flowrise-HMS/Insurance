<?php

namespace Modules\Insurance\DTOs;

final readonly class ImportResult
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public array $errors = [],
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }
}
