<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you define who can access your API via the browser (origins),
    | which methods are allowed, etc. In development, we usually allow
    | everything to make front-end development easier.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Path(s) that should receive CORS headers.
    |--------------------------------------------------------------------------
    |
    | You can use a wildcard (*) or list specific routes. In your case,
    | add "ping" to allow CORS only on that route.
    |
    */
    'paths' => [
        'api/*',
        'ping',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP methods allowed by CORS.
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => ['*'], // ['GET','POST','PUT',...]

    /*
    |--------------------------------------------------------------------------
    | Origin(s) permitted to make requests.
    |--------------------------------------------------------------------------
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),

    /*
    |--------------------------------------------------------------------------
    | Origin patterns (optional, usually empty).
    |--------------------------------------------------------------------------
    */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Headers allowed in the request.
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Headers exposed in the response.
    |--------------------------------------------------------------------------
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | How long the “preflight” request response can be cached by the browser (in seconds).
    |--------------------------------------------------------------------------
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Whether the browser should send credentials (cookies).
    |--------------------------------------------------------------------------
    */
    'supports_credentials' => false,

];
