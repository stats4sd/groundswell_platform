<?php

// config for Stats4sd/LaravelShinyLoader
return [
    'root_url' => env('SHINY_ROOT_URL', 'http://localhost:3838'),
    'root_path' => env('SHINY_ROOT_PATH', '/srv/shiny-server'),
    'auth_key' => env('SHINY_AUTH_KEY', 'change-me'),

    // Folder names of the shiny apps on the shiny server; each is served at {root_url}/{name}/.
    'apps' => [
        'groundswell_monitor',
        'groundswell_analysis',
    ],
];
