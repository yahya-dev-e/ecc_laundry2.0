/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './preview-server.js',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                centrale: {
                    teal: '#00897b',
                    'teal-dark': '#00695c',
                    'teal-darker': '#004d40',
                    'teal-light': '#e0f2f1',
                    'teal-accent': '#00b4a7',
                    green: '#2e7d32',
                    'green-light': '#4caf50',
                    bg: '#f4f7f6',
                    sidebar: '#00796b',
                    'sidebar-hover': '#00695c',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
