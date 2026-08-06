<?php

namespace Modules\Insurance\Schemes\Nhis;

use Modules\Insurance\DTOs\ValidationResult;

class NhsXmlSchemaValidator
{
    public function schemaPath(): string
    {
        return module_path('Insurance', 'tests/fixtures/nhia-claims-v8.6.xsd');
    }

    public function validate(string $xml): ValidationResult
    {
        if (! is_file($this->schemaPath())) {
            return ValidationResult::fail([
                '[XSD] NHIA claims schema file is missing.',
            ]);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument;
            if (! $document->loadXML($xml)) {
                return ValidationResult::fail($this->formatLibxmlErrors('XML parse'));
            }

            if (! $document->schemaValidate($this->schemaPath())) {
                return ValidationResult::fail($this->formatLibxmlErrors('XSD'));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return ValidationResult::pass();
    }

    /**
     * @return array<int, string>
     */
    protected function formatLibxmlErrors(string $prefix): array
    {
        $messages = [];

        foreach (libxml_get_errors() as $error) {
            $messages[] = sprintf('[%s] %s', $prefix, trim($error->message));
        }

        return $messages === []
            ? [sprintf('[%s] Validation failed.', $prefix)]
            : $messages;
    }
}
