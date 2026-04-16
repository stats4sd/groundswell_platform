import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/**/*.php',
        './resources/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './resources/**/*.vue',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                'green': 'var(--green)',
                'blue': 'var(--blue)',
                'dark-blue': 'var(--dark-blue)',
                'orange': 'var(--orange)',
                'dark-orange': 'var(--dark-orange)',
                'light-grey': 'var(--light-grey)',
                'grey': 'var(--grey)',
                'lightgreen': 'var(--lightgreen)',
                'hyellow': 'var(--hyellow)',
                'light-orange': 'var(--light-orange)'
            },
            fontFamily: {
                montserrat: ['"Montserrat"', "sans-serif"],
            },
        },
        // borderRadius: {
        //     DEFAULT: '0',
        // }
    },
}
