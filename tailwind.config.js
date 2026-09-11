import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Modules/**/Views/**/*.blade.php',
        './app/Modules/**/Enums/*.php',
    ],

    theme: {
        extend: {
            // Sistema "Modernist": tinta sobre gris claro, un solo acento rojo,
            // cero radios, bordes fuertes. Tokens tomados del design system.
            colors: {
                ink: '#201e1d',
                ground: '#f3f2f2',
                surface: '#eae9e9',
                accent: {
                    DEFAULT: '#ec3013',
                    100: '#fff2ef',
                    200: '#ffe0d9',
                    300: '#ffc4b8',
                    400: '#ff9783',
                    500: '#ff563c',
                    600: '#dd2b0f',
                    700: '#ae1800',
                    800: '#7c1405',
                    900: '#4d170e',
                },
                neutral: {
                    100: '#f8f4f4',
                    200: '#eae7e7',
                    300: '#d7d3d3',
                    400: '#bab6b6',
                    500: '#9b9797',
                    600: '#7d7979',
                    700: '#605d5d',
                    800: '#444141',
                    900: '#2d2b2b',
                },
            },
            fontFamily: {
                sans: ['Archivo', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
