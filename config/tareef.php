<?php

return [

    /*
    |---------------------------------------------------------------------------
    | API Key
    |---------------------------------------------------------------------------
    |
    | Issue one from the Tareef dashboard → API keys. Treat it like a password
    | (it's a bearer token). Use a separate key per environment so you can
    | rotate without taking everything offline.
    |
    */
    'api_key' => env('TAREEF_API_KEY'),

    /*
    |---------------------------------------------------------------------------
    | Base URL
    |---------------------------------------------------------------------------
    |
    | The Tareef API root, without a trailing slash. Override for a
    | self-hosted Tareef deployment or for local-dev pointing at your own
    | dashboard instance.
    |
    */
    'base_url' => env('TAREEF_BASE_URL', 'https://tareef.io'),

    /*
    |---------------------------------------------------------------------------
    | HTTP timeout (seconds)
    |---------------------------------------------------------------------------
    |
    | How long a single request is allowed to take before we give up. Verify
    | calls are typically <300 ms, but register calls with many images can be
    | slower while embeddings are extracted upstream.
    |
    */
    'timeout' => env('TAREEF_TIMEOUT', 30),

    /*
    |---------------------------------------------------------------------------
    | Retries
    |---------------------------------------------------------------------------
    |
    | How many times to retry transient network failures (connect timeout,
    | DNS error, 5xx) before throwing. Set to 0 to disable.
    |
    */
    'retries' => env('TAREEF_RETRIES', 2),

    /*
    |---------------------------------------------------------------------------
    | Throw on quota errors
    |---------------------------------------------------------------------------
    |
    | When false, the SDK swallows 429 responses and returns a generic failure
    | result instead. When true (default), it throws QuotaExceededException so
    | your app can catch and surface a billing-aware message.
    |
    */
    'throw_on_quota' => env('TAREEF_THROW_ON_QUOTA', true),

];
