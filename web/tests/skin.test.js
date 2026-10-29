// The skin (js/theme-boot.js; docs/skins-plan.md): <html data-skin> before paint, "game" unless
// "serious" was chosen, kept with the theme's other choices and kept when they change; game mode is
// always dark, keeping the person's own light or dark for serious mode.
import { assert, test } from './harness.js';

async function boot() {
    if (window.stackapellaTheme?.choose) return window.stackapellaTheme;
    await new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = '/js/theme-boot.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.append(script);
    });
    return window.stackapellaTheme;
}

test('skin: game unless serious is chosen, kept through other choices, and back again', async () => {
    const theme = await boot();
    const kept = localStorage.getItem('stackapella.appearance');
    try {
        localStorage.removeItem('stackapella.appearance');
        document.cookie = 'sa_theme=; path=/; max-age=0';
        theme.apply();
        assert(document.documentElement.dataset.skin === 'game', `the default: ${document.documentElement.dataset.skin}`);
        theme.choose({ skin: 'serious' });
        assert(document.documentElement.dataset.skin === 'serious', 'serious chosen');
        theme.choose({ mode: 'light' });
        assert(document.documentElement.dataset.skin === 'serious' && theme.chosen().skin === 'serious', 'a new mode kept the skin');
        assert(document.documentElement.dataset.theme.endsWith('-light'), 'serious and light shows light');
        theme.choose({ skin: 'game' });
        assert(document.documentElement.dataset.theme.endsWith('-dark') && theme.chosen().mode === 'light', 'game mode is always dark, and the light choice is kept');
        theme.choose({ skin: 'serious' });
        assert(document.documentElement.dataset.theme.endsWith('-light'), 'serious again: light again');
        theme.choose({ skin: 'nonsense' });
        assert(document.documentElement.dataset.skin === 'game', 'anything else is game');
    } finally {
        if (kept === null) localStorage.removeItem('stackapella.appearance');
        else localStorage.setItem('stackapella.appearance', kept);
        document.cookie = 'sa_theme=; path=/; max-age=0';
        theme.apply();
    }
});
