/** @type {import('tailwindcss').Config} */
import brandPreset from './tailwind.brand.cjs';

export default {
  presets: [brandPreset],
  darkMode: 'class',
  content: [
    './src/**/*.html',
    './src/js/**/*.js',
    './src/js/**/*.mjs',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};

