<?php
/**
 * includes/theme_head.php — shared <head> theming for every page.
 * Defines the Tailwind config, the light/dark CSS variables, the favicon
 * and the no-flash theme bootstrap. Dark is the default theme.
 * Include this inside <head> AFTER the <title>.
 */
?>
<link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Crect%20width='32'%20height='32'%20rx='7'%20fill='%23ffa116'/%3E%3Ctext%20x='16'%20y='23'%20font-family='Arial'%20font-size='19'%20font-weight='bold'%20fill='%231a1a1a'%20text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">

<!-- Set the theme before paint to avoid a flash (dark is the default). -->
<script>
(function () {
    try {
        var t = localStorage.getItem('theme') || 'dark';
        document.documentElement.classList.toggle('dark', t === 'dark');
    } catch (e) { document.documentElement.classList.add('dark'); }
})();
function toggleTheme() {
    var dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
}
</script>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                colors: {
                    // Semantic colors driven by CSS variables (theme-aware)
                    bg:       'var(--bg)',
                    card:     'var(--card)',
                    elevated: 'var(--elevated)',
                    fg:       'var(--fg)',
                    muted:    'var(--muted)',
                    line:     'var(--line)',
                    // Fixed brand / status colors
                    brand:  '#ffa116',
                    accent: '#2f81f7',
                    easy:   '#1cb8a8',
                    medium: '#ffb700',
                    hard:   '#f63737',
                    done:   '#2ea043',
                },
            },
        },
    };
</script>

<style>
    :root {
        color-scheme: light;
        --bg:       #f5f6f8;
        --card:     #ffffff;
        --elevated: #eceef1;
        --fg:       #15181c;
        --muted:    #5b636e;
        --line:     #e3e6ea;
    }
    .dark {
        color-scheme: dark;
        --bg:       #1a1a1a;
        --card:     #262626;
        --elevated: #2f2f2f;
        --fg:       #e9e9ea;
        --muted:    #9aa0a6;
        --line:     #3a3a3a;
    }
    body { -webkit-font-smoothing: antialiased; }
    /* Theme native form controls in dark mode without per-field classes */
    .dark input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
    .dark select,
    .dark textarea {
        background-color: var(--elevated);
        color: var(--fg);
        border-color: var(--line);
    }
    .dark option { background-color: var(--card); color: var(--fg); }
    ::-webkit-scrollbar { width: 10px; height: 10px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--line); border-radius: 9999px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--muted); }
    ::selection { background: rgba(47,129,247,.30); }
</style>
