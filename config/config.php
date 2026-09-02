<?php

return [
    'name' => 'Insurance',
    'enabled' => env('INSURANCE_MODULE_ENABLED', true),
    'nhis' => [
        'feedback_secret' => env('NHIS_FEEDBACK_SECRET'),
        'xml_version' => env('NHIS_XML_VERSION', '8.6'),
    ],
    'otac' => [
        'base_url' => env('NHIS_OTAC_BASE_URL', 'https://otac.nhia.gov.gh'),
        'timeout' => (int) env('NHIS_OTAC_TIMEOUT', 15),
    ],
    'queues' => [
        'claims' => env('INSURANCE_CLAIMS_QUEUE', 'insurance-claims'),
        'catalog_sync' => env('INSURANCE_CATALOG_QUEUE', 'insurance-sync'),
    ],
];
