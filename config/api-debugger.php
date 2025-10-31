<?php

return [
    'enabled' => (bool) env('API_DEBUGGER_ENABLED', env('APP_DEBUG', false)),
    /**
     * Specify what data to collect.
     */
    'collections' => [
    ],

    'response_key' => env('API_DEBUGGER_KEY', 'debug')
];
