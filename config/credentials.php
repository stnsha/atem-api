<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Credentials
    |--------------------------------------------------------------------------
    |
    | This file stores credentials (usernames, passwords, tokens) for the
    | external services this application talks to. Connection URLs and
    | other non-secret configuration belong in config/services.php.
    |
    */

    'odb_api' => [
        'username' => env('ODB_API_USERNAME', ''),
        'password' => env('ODB_API_PASSWORD', ''),
    ],

    'tableau' => [
        'username' => env('TABLEAU_USERNAME', ''),
        'password' => env('TABLEAU_PASSWORD', ''),
    ],

    'super_admin' => [
        'email'    => env('SUPER_ADMIN_EMAIL', ''),
        'password' => env('SUPER_ADMIN_PASSWORD', ''),
    ],

    'atem_service' => [
        'email'    => env('ATEM_SERVICE_EMAIL', 'atem-service@local'),
        'password' => env('ATEM_SERVICE_PASSWORD', 'atem-service-local'),
    ],

];
