<?php

return [
    'service_name' => env('PLACOWKA_LEGAL_SERVICE_NAME', 'Placówka Online'),
    'service_url' => env('PLACOWKA_LEGAL_SERVICE_URL', 'https://monitoring.wcag-cms.pl'),
    'controller_name' => env('PLACOWKA_LEGAL_CONTROLLER_NAME', 'Adam Trojanowski'),
    'contact_email' => env('PLACOWKA_LEGAL_CONTACT_EMAIL', 'it@it-serwis.net'),
    'controller_address' => env('PLACOWKA_LEGAL_CONTROLLER_ADDRESS'),
    'iod_email' => env('PLACOWKA_LEGAL_IOD_EMAIL'),
    'source_code_url' => env('PLACOWKA_SOURCE_CODE_URL', 'https://github.com/wcagcms/placowka-online'),
    'open_source_license' => env(
        'PLACOWKA_OPEN_SOURCE_LICENSE',
        'GNU AGPL-3.0-or-later'
    ),
    'code_signing_name' => env(
        'PLACOWKA_CODE_SIGNING_NAME',
        'Open Source Code Signing'
    ),
    'effective_date' => env('PLACOWKA_LEGAL_EFFECTIVE_DATE', '19 lipca 2026 r.'),
    'version' => env('PLACOWKA_LEGAL_VERSION', '1.0'),
];
