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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'octopus' => [
        'api_url' => in_array(env('APP_ENV'), ['production', 'staging'])
            ? env('ODB_API_URL_PROD')
            : env('ODB_API_URL_LOCAL'),
    ],

    'tableau' => [
        'base_url'    => env('TABLEAU_BASE_URL'),
        'api_version' => env('TABLEAU_API_VERSION'),
        'site_id'     => env('TABLEAU_SITE_ID'),
        'view_url'    => env('TABLEAU_VIEW_URL'),
    ],

];
