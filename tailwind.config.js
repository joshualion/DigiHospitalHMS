import defaultTheme from 'tailwindcss/defaultTheme';
import aspectRatio from '@tailwindcss/aspect-ratio';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class', // enable class-based dark mode
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
     extend: {
      colors: {
        blood: {
          DEFAULT: '#B22222', // blood red
          light: '#DC2626',
          dark: '#7F1D1D',
        },
        healing: '#22C55E',   // green for health/recovery
        calm: '#0EA5E9',      // hospital blue
        alert: '#F59E0B',     // yellow for alerts/warnings
        Seagrass: '#0697A1FF',    //  Seagrass
      },
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
      },
    },
  },

    plugins: [forms, typography, aspectRatio],
};
