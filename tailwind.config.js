import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },
            colors: {
                app:    'var(--bg-app)',
                panel:  'var(--bg-panel)',
                sunken: 'var(--bg-sunken)',
                hover:  'var(--bg-hover)',
                active: 'var(--bg-active)',
                border: 'var(--border)',
                'border-strong': 'var(--border-strong)',
                fg:     'var(--fg)',
                'fg-muted':   'var(--fg-muted)',
                'fg-subtle':  'var(--fg-subtle)',
                accent: 'var(--accent)',
                'accent-hover': 'var(--accent-hover)',
                'accent-soft':  'var(--accent-soft)',
                'status-todo':     'var(--status-todo)',
                'status-progress': 'var(--status-progress)',
                'status-review':   'var(--status-review)',
                'status-done':     'var(--status-done)',
                'status-blocked':  'var(--status-blocked)',
            },
            boxShadow: {
                sm: 'var(--shadow-sm)',
                md: 'var(--shadow-md)',
                lg: 'var(--shadow-lg)',
            },
        },
    },

    plugins: [forms],
};
