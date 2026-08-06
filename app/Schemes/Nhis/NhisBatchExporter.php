<?php

namespace Modules\Insurance\Schemes\Nhis;

use Illuminate\Support\Facades\Storage;
use Modules\Insurance\DTOs\ExportedFile;
use Modules\Insurance\Models\ClaimBatch;

class NhisBatchExporter
{
    public function __construct(
        protected NhisBatchXmlEncoder $encoder,
        protected NhsXmlSchemaValidator $schemaValidator,
    ) {}

    public function export(ClaimBatch $batch): ExportedFile
    {
        $xml = $this->encoder->encode($batch);
        $schemaResult = $this->schemaValidator->validate($xml);

        if (! $schemaResult->valid) {
            throw new \InvalidArgumentException(
                'NHIS batch XML failed XSD validation: '.implode(' ', $schemaResult->errors)
            );
        }

        $filename = sprintf('nhis-batch-%s.xml', $batch->batch_number);
        $path = 'insurance/exports/'.$filename;

        $disk = Storage::disk('local');
        $disk->makeDirectory('insurance/exports');
        $disk->put($path, $xml);

        return new ExportedFile(
            path: $path,
            filename: $filename,
        );
    }
}
