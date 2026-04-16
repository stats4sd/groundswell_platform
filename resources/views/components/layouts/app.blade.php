<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'HOLPA' }}</title>

    @filamentStyles
    @vite('resources/css/filament/app/theme.css')
    @vite('resources/css/cover-page.css')

    {{ $viteScripts ?? '' }}

</head>
<body>
    {{ $slot }}

    @livewire('notifications')

@filamentScripts
</body>
</html>
