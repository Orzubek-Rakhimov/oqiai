<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The addresses (or CIDR ranges, comma separated) whose X-Forwarded-*
    | headers are believed: on the VPS, Coolify's proxy and nginx, which
    | reach the app from Docker's private networks. Empty in development,
    | where nothing stands in front of it. Whether a request came over HTTPS
    | decides the session cookie and every link the app writes, and the
    | caller's address is what the login lockout counts.
    |
    */

    'proxies' => (string) env('TRUSTED_PROXIES', ''),

];
