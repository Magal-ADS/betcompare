<?php

return [

    'bookmakers' => [
        'firebets' => [
            'name' => 'Firebets',
            'games_url' => env('FIREBETS_GAMES_URL', 'https://firebets.net.br/sistema_v2/usuarios/simulador/desktop/jogos.aspx?idesporte=102&idcampeonato=574926'),
            'website_url' => 'https://firebets.net.br',
            'is_primary' => true,
        ],
        'chute13' => [
            'name' => 'Chute13',
            'games_url' => env('CHUTE13_GAMES_URL', 'https://chute13.net'),
            'website_url' => 'https://chute13.net',
            'is_primary' => false,
        ],
        'a2bets' => [
            'name' => 'A2Bets',
            'games_url' => env('A2BETS_GAMES_URL', 'https://a2bets.com'),
            'website_url' => 'https://a2bets.com',
            'is_primary' => false,
        ],
        'gbgoldbet' => [
            'name' => 'GB Gold Bet',
            'games_url' => env('GBGOLDBET_GAMES_URL', 'https://gbgoldbet.com'),
            'website_url' => 'https://gbgoldbet.com',
            'is_primary' => false,
        ],
    ],

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

];
