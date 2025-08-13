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

    'providers' => [
        'line' => [
            'client_id' => env('LINE_CHANNEL_ID'),
            'client_secret' => env('LINE_CHANNEL_SECRET'),
            'redirect' => env('LINE_REDIRECT_URI'),
        ],
    ],

];
