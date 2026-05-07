<?php

return [
    'name' => 'Insurance',
    'enabled' => env('INSURANCE_MODULE_ENABLED', true),
    'nhis' => [
        'submission_endpoint' => env('NHIS_SUBMISSION_ENDPOINT'),
        'feedback_endpoint' => env('NHIS_FEEDBACK_ENDPOINT'),
        'token' => env('NHIS_TOKEN'),
        'feedback_secret' => env('NHIS_FEEDBACK_SECRET'),
        'timeout' => (int) env('NHIS_TIMEOUT', 15),
        'xml_version' => env('NHIS_XML_VERSION', '8.6'),
    ],
    'queues' => [
        'claims' => env('INSURANCE_CLAIMS_QUEUE', 'insurance-claims'),
        'catalog_sync' => env('INSURANCE_CATALOG_QUEUE', 'insurance-sync'),
    ],
];
