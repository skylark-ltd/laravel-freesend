<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Freesend API Key
    |--------------------------------------------------------------------------
    |
    | Your Freesend API key for authenticating requests.
    | You can obtain this from your Freesend dashboard.
    |
    */

    "api_key" => env("FREESEND_API_KEY"),

    /*
    |--------------------------------------------------------------------------
    | Freesend API Endpoint
    |--------------------------------------------------------------------------
    |
    | The URL endpoint for the Freesend API. Override this if you
    | need to use a different server or a self-hosted instance.
    |
    */

    "endpoint" => env(
        "FREESEND_ENDPOINT",
        "https://freesend.metafog.io/api/send-email",
    ),
];
