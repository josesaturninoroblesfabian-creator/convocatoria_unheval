<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'profile_registry' => [
        'enabled' => env('PROFILE_REGISTRY_LOOKUP_ENABLED', false),
        'apisperu_token' => env('APISPERU_DNI_TOKEN'),
        'apisted_token' => env('APISTED_TOKEN'),
        // Cuando es true (recomendado), las consultas externas solo se permiten
        // desde el servidor local. Para producción, desactívalo y asegura los
        // tokens y el límite de consultas antes de exponer el endpoint.
        'restrict_to_local' => env('PROFILE_REGISTRY_RESTRICT_TO_LOCAL', true),
    ],

    // Integración futura con el SSO institucional y el escalafón docente.
    // Se dejan los parámetros listos; el flujo de inicio de sesión debe
    // implementarse contra el proveedor OIDC/SAML que la universidad publique.
    'sso' => [
        'enabled' => env('SSO_ENABLED', false),
        'issuer' => env('SSO_ISSUER'),
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        'redirect' => env('SSO_REDIRECT_URI'),
        'escalafon_endpoint' => env('ESCALAFON_ENDPOINT'),
        'escalafon_token' => env('ESCALAFON_TOKEN'),
    ],

];
