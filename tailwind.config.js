/** @type {import('tailwindcss').Config} */
import preset from './vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './resources/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/Http/Livewire/**/*.php',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}
