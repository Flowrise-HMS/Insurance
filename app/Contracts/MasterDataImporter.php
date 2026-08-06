<?php

namespace Modules\Insurance\Contracts;

use Modules\Insurance\DTOs\ImportResult;

interface MasterDataImporter
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function import(string $path, array $options = []): ImportResult;
}
