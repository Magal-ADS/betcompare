<?php

return [
    'access' => [
        'enabled' => env('ODDRADAR_AUTH_ENABLED', env('APP_ENV') !== 'local'),
        'username' => env('ODDRADAR_AUTH_USERNAME'),
        'password' => env('ODDRADAR_AUTH_PASSWORD'),
    ],
];
