/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50:  '#eff5ff',
          100: '#d9e6ff',
          200: '#b8cfff',
          300: '#8aadff',
          400: '#5a87ff',
          500: '#3b63eb',
          600: '#2946c9',
          700: '#1f3aa0',
          800: '#1c3380',
          900: '#192a64',
        },
        accent: '#f59f2a',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
