<?php

use BetterFuturesStudio\FilamentLocalLogins\Filament\Pages\Auth\LoginPage;

return [
    'panels' => [
        'app' => [
            'enabled' => env('ADMIN_PANEL_LOCAL_LOGINS_ENABLED', env('APP_ENV') === 'local'),
            'emails' => array_filter(array_map('trim', explode(',', env('ADMIN_PANEL_LOCAL_LOGIN_EMAILS', '')))),
            'login_page' => \App\Filament\App\Pages\Auth\Login::class,
        ],
    ],
];
