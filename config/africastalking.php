<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Africa's Talking Credentials
    |--------------------------------------------------------------------------
    | These values are sourced exclusively from environment variables so they
    | are never committed to version control.
    */
    'username'  => env('AFRICAS_TALKING_USERNAME', 'sandbox'),
    'api_key'   => env('AFRICAS_TALKING_API_KEY'),
    'sender_id' => env('AFRICAS_TALKING_SENDER_ID'),
];