<?php

// config for  Stats4sd/LaravelShinyLoader
return [
    'app-path' => env('SHINY_APP_PATH', base_path('shiny')),
    'auth-key' => env('SHINY_AUTH_KEY', 'change-me'),

    ## Add the urls to every shiny app that will be embedded here, and the .env KEYs that hold them, e.g.:
    'monitoring-app-url' => env('SHINY_APP_URL_MONITORING', 'http://127.0.0.1:7007'),
    'analysis-app-url' => env('SHINY_APP_URL_ANALYSIS', 'http://127.0.0.1:7009'),

];
