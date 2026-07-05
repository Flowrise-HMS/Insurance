<?php

namespace Modules\Insurance\DTOs;

final readonly class ExportedFile
{
    public function __construct(
        public string $path,
        public string $filename,
        public string $mimeType = 'application/xml',
    ) {}
}
