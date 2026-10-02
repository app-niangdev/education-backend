<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    // WAHA (WhatsApp HTTP API) : envoi des recus et factures aux tuteurs.
    // Sans URL ni cle, aucun envoi n'est tente — la caisse fonctionne a
    // l'identique.
    'waha' => [
        'base_url' => rtrim((string) env('WAHA_BASE_URL', ''), '/'),
        'api_key'  => (string) env('WAHA_API_KEY', ''),
        'session'  => (string) env('WAHA_SESSION', 'default'),
        'timeout'  => (int) env('WAHA_TIMEOUT', 20),

        // Les telephones des tuteurs sont ranges sans indicatif (voir
        // Tuteur::normaliserTelephone) : WhatsApp, lui, l'exige.
        'country_code'           => (string) env('WAHA_COUNTRY_CODE', '221'),
        'national_number_length' => (int) env('WAHA_NATIONAL_NUMBER_LENGTH', 9),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
