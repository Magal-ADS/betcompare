<?php

return [
    'collection_detail_concurrency' => (int) env('ODDRADAR_COLLECTION_DETAIL_CONCURRENCY', 2),
    'collection_cooldown_minutes' => (int) env('ODDRADAR_COLLECTION_COOLDOWN_MINUTES', 30),
    'source_rate_limit_backoff_minutes' => [60, 180, 360, 720],
    'source_rate_limit_max_minutes' => (int) env('ODDRADAR_SOURCE_RATE_LIMIT_MAX_MINUTES', 1440),

    'super_admin' => [
        'name' => env('ODDRADAR_SUPER_ADMIN_NAME', 'Administrador OddRadar'),
        'email' => env('ODDRADAR_SUPER_ADMIN_EMAIL', 'jbarbosafenerick@gmail.com'),
        'password' => env('ODDRADAR_SUPER_ADMIN_PASSWORD'),
    ],
];
