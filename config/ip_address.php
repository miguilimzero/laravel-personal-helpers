<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IpAddress Driver Name
    |--------------------------------------------------------------------------
    |
    | IPInfo driver service name.
    |
    */
    'driver' => env('IP_ADDRESS_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | IpAddress Authentication Key
    |--------------------------------------------------------------------------
    |
    | IPInfo generated private key.
    |
    */
    'key' => env('IP_ADDRESS_DRIVER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | IpAddress Cache Duration
    |--------------------------------------------------------------------------
    |
    | IPInfo cache duration until it get re-validated (in minutes).
    |
    */
    'cache_duration' => env('IP_ADDRESS_CACHE_DURATION', 60 * 24),
];
