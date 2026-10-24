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

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // Cadangan kalau belum diisi di admin > Setelan (dokumentasi match dari Google Drive).
        'drive_api_key' => env('GOOGLE_DRIVE_API_KEY'),
    ],

    'sumopod' => [
        'api_key' => env('SUMOPOD_API_KEY'),
        // Sandbox Sumopod pakai domain BEDA (api-pay-sandbox.sumopod.com), bukan cuma API key
        // beda di domain yang sama seperti dugaan awal dari dokumentasi -- ketemu langsung dari
        // dashboard Sumopod (tab Quick Start > Sandbox), tidak disebutkan di dokumentasi yang
        // diberikan. SALAH pakai domain live+key sandbox = 401 meski key-nya benar.
        'base_url' => env('SUMOPOD_BASE_URL', 'https://api-pay.sumopod.com/api/v1'),
        'webhook_token' => env('SUMOPOD_WEBHOOK_TOKEN'),
        'sandbox_bypass' => env('SUMOPOD_SANDBOX_BYPASS', false),
    ],

    // Gerbang akses staging (email+PIN dikelola admin lewat tab Sandbox di panel), pengganti
    // Cloudflare Access -- HARUS tetap false di produksi/lokal/CI, hanya dinyalakan di .env
    // staging. Lihat App\Http\Middleware\SandboxGate.
    'sandbox_gate' => [
        'enabled' => env('SANDBOX_GATE_ENABLED', false),
    ],

];
