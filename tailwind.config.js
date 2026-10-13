/** @type {import('tailwindcss').Config} */
// AtGlance design tokens (design-doc.md). Same palette as the Management Console and the
// Filament theme (resources/views/filament/theme-styles.blade.php).
export default {
    content: [
        './resources/views/**/*.blade.php',
        './app/View/**/*.php',
        './app/Livewire/**/*.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Neue Haas Grotesk Text"', 'Inter', '"Helvetica Neue"', 'Helvetica', 'Arial', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },
            borderRadius: {
                card: '20px',
                frame: '28px',
                input: '12px',
            },
            boxShadow: {
                ag: '0 8px 30px rgba(17, 20, 24, 0.06)',
                'ag-strong': '0 12px 40px rgba(17, 20, 24, 0.12)',
                'ag-glow': '0 6px 18px rgba(76, 201, 255, 0.35)',
                'ag-pill': '0 4px 14px rgba(76, 201, 255, 0.35)',
                'ag-focus': '0 0 0 4px rgba(113, 247, 212, 0.3)',
            },
            backgroundImage: {
                'ag-gradient': 'linear-gradient(135deg, #71F7D4 0%, #4CC9FF 100%)',
                'ag-gradient-hover': 'linear-gradient(135deg, #5FF0C9 0%, #2FBAF2 100%)',
            },
            letterSpacing: { heading: '-0.02em' },
            colors: {
                ag: {
                    sky: '#4CC9FF',
                    teal: '#2CB7D9',
                    'teal-text': '#0F7F96',
                    mint: '#71F7D4',
                    ink: '#14171B',
                    'ink-hover': '#262A30',
                    'ink-soft': '#B7BEC6',
                    bg: '#EEF1F4',
                    surface: '#F4F6F8',
                    line: '#E6E9EE',
                    muted: '#8A9099',
                    subtle: '#5B626B',
                    success: '#1FA874',
                    'success-soft': '#E3FAF3',
                    'success-text': '#137A54',
                    danger: '#E45757',
                    'danger-soft': '#FDECEC',
                    'danger-hover': '#F9D6D6',
                    'danger-text': '#B33B3B',
                    warning: '#D98A0B',
                    'warning-soft': '#FEF4E2',
                    'warning-text': '#8A5A06',
                    info: '#2CB7D9',
                    'info-soft': '#E4F6FF',
                    'accent-soft': '#E5FCF6',
                },
            },
        },
    },
    plugins: [],
};
