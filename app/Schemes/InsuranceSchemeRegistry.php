<?php

namespace Modules\Insurance\Schemes;

use InvalidArgumentException;
use Modules\Insurance\Contracts\InsuranceSchemeContract;

class InsuranceSchemeRegistry
{
    /** @var array<string, InsuranceSchemeContract> */
    protected array $schemes = [];

    public function register(InsuranceSchemeContract $scheme): void
    {
        $this->schemes[$scheme->code()] = $scheme;
    }

    public function forCode(string $code): InsuranceSchemeContract
    {
        if (! isset($this->schemes[$code])) {
            throw new InvalidArgumentException("Unsupported insurance scheme [{$code}]");
        }

        return $this->schemes[$code];
    }

    /**
     * @return array<string, InsuranceSchemeContract>
     */
    public function all(): array
    {
        return $this->schemes;
    }

    /**
     * @return array<string, InsuranceSchemeContract>
     */
    public function enabled(): array
    {
        return array_filter($this->schemes, fn (InsuranceSchemeContract $scheme) => $scheme->isEnabled());
    }
}
