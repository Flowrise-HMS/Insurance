<?php

namespace Modules\Insurance\Validation;

use Illuminate\Support\Collection;

final readonly class ValidationReport
{
    /**
     * @param  array<int, ValidationItem>  $items
     */
    public function __construct(
        public array $items = [],
    ) {}

    public function valid(): bool
    {
        return $this->errors()->isEmpty();
    }

    public function errors(): Collection
    {
        return collect($this->items)->filter(fn (ValidationItem $item) => $item->isError())->values();
    }

    public function warnings(): Collection
    {
        return collect($this->items)->reject(fn (ValidationItem $item) => $item->isError())->values();
    }

    /**
     * @return array{valid: bool, errors: array<int, array{code: string, message: string, claim_number: ?string}>, warnings: array<int, array{code: string, message: string, claim_number: ?string}>}
     */
    public function toArray(): array
    {
        $map = fn (ValidationItem $item) => [
            'code' => $item->code,
            'message' => $item->message,
            'claim_number' => $item->claimNumber,
        ];

        return [
            'valid' => $this->valid(),
            'errors' => $this->errors()->map($map)->all(),
            'warnings' => $this->warnings()->map($map)->all(),
        ];
    }
}
