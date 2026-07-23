<?php

$applicationUrl = (string) env('APP_URL', 'http://localhost');
$applicationHost = parse_url($applicationUrl, PHP_URL_HOST) ?: 'localhost';
$defaultTrustedHost = '^'.preg_quote($applicationHost, '/').'$';

$trustedHosts = array_values(array_filter(array_map(
    static fn (string $host): string => trim($host),
    explode(',', (string) env('APP_TRUSTED_HOSTS', $defaultTrustedHost))
)));

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted HTTP Host headers
    |--------------------------------------------------------------------------
    |
    | Values are regular expressions without delimiters. Multiple patterns can
    | be provided in APP_TRUSTED_HOSTS and separated with commas.
    |
    */
    'trusted_hosts' => $trustedHosts,

    /*
    |--------------------------------------------------------------------------
    | Browser security policy
    |--------------------------------------------------------------------------
    */
    'content_security_policy' => implode(' ', [
        "default-src 'self';",
        "script-src 'self';",
        "style-src 'self' 'unsafe-inline';",
        "img-src 'self' data:;",
        "font-src 'self';",
        "connect-src 'self';",
        "frame-src 'none';",
        "media-src 'none';",
        "object-src 'none';",
        "base-uri 'self';",
        "form-action 'self';",
        "frame-ancestors 'none';",
        "manifest-src 'self';",
        "worker-src 'self';",
    ]),

    'hsts_max_age' => max(0, (int) env('SECURITY_HSTS_MAX_AGE', 31536000)),
];
