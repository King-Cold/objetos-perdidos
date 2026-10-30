import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                tecnm: {
                    wine: {
                        DEFAULT: '#691C32',
                        hover: '#531527',
                        light: '#8B2643',
                        dark: '#3D0C1B',
                    },
                    gold: {
                        DEFAULT: '#BC955C',
                        hover: '#9E7B45',
                        light: '#D4AF37',
                        soft: '#F7F1E5',
                    },
                },
            },
        },
    },

    plugins: [forms],
};
