<?php

return [
    'key' => env('TRANSLATIONIO_KEY'),
    'source_locale' => 'en',
    'target_locales' => ['fr', 'es'],

    'gettext_parse_paths' => ['app', 'resources', 'packages']
];