<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom URL scheme
    |--------------------------------------------------------------------------
    | Used to hand control back from the system browser after login. Must match
    | the scheme registered in capacitor.config.ts and AndroidManifest.xml.
    */

    'scheme' => env('NATIVE_APP_SCHEME', 'rebit'),

    /*
    |--------------------------------------------------------------------------
    | Token lifetime, in days
    |--------------------------------------------------------------------------
    | Set per token at creation rather than through config/sanctum.php's global
    | `expiration`, which would also apply to anything else minting tokens.
    | There is no refresh token: users re-authenticate when this elapses.
    */

    'token_days' => (int) env('NATIVE_TOKEN_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Handoff windows, in seconds
    |--------------------------------------------------------------------------
    | `handoff` is how long the user has to get through login, signup or
    | onboarding in the browser tab. `code` is the exchange window, which only
    | has to cover one HTTPS round trip and so is deliberately short.
    */

    'handoff_ttl' => (int) env('NATIVE_HANDOFF_TTL', 900),
    'code_ttl' => (int) env('NATIVE_CODE_TTL', 120),

];
