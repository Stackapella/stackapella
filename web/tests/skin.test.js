// The skin (js/theme-boot.js; docs/skins-plan.md): <html data-skin> before paint, "game" unless
// "serious" was chosen, kept with the theme's other choices and kept when they change; game mode has its own
// light or dark (dark until chosen, on the site too since 2026-11-02), keeping serious mode's apart.
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
        // No light or dark chosen: serious mode is the device's (Hillel, 2026-11-17: "Auto everywhere, calm"), and turning
        // game mode off stores no dark of its own.
        const device = matchMedia('(prefers-color-scheme: dark)').matches ? '-dark' : '-light';
        assert(theme.chosen().mode === null && document.documentElement.dataset.theme.endsWith(device), `serious, nothing chosen, is the device's ${device}: ${document.documentElement.dataset.theme}, ${theme.chosen().mode}`);
        theme.choose({ mode: 'light' });
        assert(document.documentElement.dataset.skin === 'serious' && theme.chosen().skin === 'serious', 'a new mode kept the skin');
        assert(document.documentElement.dataset.theme.endsWith('-light'), 'serious and light shows light');
        theme.choose({ skin: 'game' });
        assert(document.documentElement.dataset.theme.endsWith('-dark') && theme.chosen().mode === 'light', 'game mode is dark until chosen, and serious mode\'s light is kept');
        theme.choose({ mode: 'light' });
        assert(document.documentElement.dataset.theme.endsWith('-light') && theme.chosen().gameMode === 'light', 'light chosen in game mode is game mode\'s own');
        theme.choose({ gameMode: 'dark' });
        assert(document.documentElement.dataset.theme.endsWith('-dark'), 'and back to dark');
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

test('skin: a light or dark picked in the default game look stores no skin, so it is no choice of game mode', async () => {
    const theme = await boot();
    const kept = localStorage.getItem('stackapella.appearance');
    try {
        localStorage.removeItem('stackapella.appearance');
        document.cookie = 'sa_theme=; path=/; max-age=0';
        theme.apply();
        theme.choose({ gameMode: 'light' });
        assert(theme.chosen().skin === null && theme.chosen().gameMode === 'light', `light kept, no skin: ${JSON.stringify(theme.chosen())}`);
        assert(document.documentElement.dataset.skin === 'game' && document.documentElement.dataset.theme.endsWith('-light'), 'still the game look, now light');
        theme.choose({ mode: 'dark' });
        assert(theme.chosen().skin === null && theme.chosen().gameMode === 'dark' && theme.chosen().mode === null, `a mode in the default look is game mode's own: ${JSON.stringify(theme.chosen())}`);
        theme.choose({ skin: 'game' });
        assert(theme.chosen().skin === 'game', 'turned on: chosen');
        theme.choose({ gameMode: 'light' });
        assert(theme.chosen().skin === 'game', 'and kept through a light or dark after');
    } finally {
        if (kept === null) localStorage.removeItem('stackapella.appearance');
        else localStorage.setItem('stackapella.appearance', kept);
        document.cookie = 'sa_theme=; path=/; max-age=0';
        theme.apply();
    }
});

test('appearance: signed in, a choice made since the device and the account last agreed wins; else the account', async () => {
    const { accountOrDevice } = await import('/js/stores/appearance.js');
    const serious = { skin: 'serious' };
    const game = { skin: 'game' };
    assert(accountOrDevice({ ...game, at: 500 }, game, 100) === 'same', 'the same skin: nothing to do');
    assert(accountOrDevice({ ...serious, at: 500 }, game, 100) === 'device', 'game mode turned off on the site after they agreed: the device wins');
    assert(accountOrDevice({ ...serious, at: 50 }, game, 100) === 'account', 'the account changed since (on another device): the account wins');
    assert(accountOrDevice({ ...serious, at: 500 }, game, null) === 'account', 'never agreed: the account, as a new device');
    assert(accountOrDevice({ skin: 'game', mode: 'light', colour: 'warm', at: 500 }, { skin: 'game', mode: 'dark', colour: 'warm' }, 100) === 'device', 'a newer look too');
});

test('theme: of two sa_theme cookies, the newest choice wins, not the first listed', () => {
    const theme = window.stackapellaTheme;
    const kept = localStorage.getItem('stackapella.appearance');
    try {
        localStorage.removeItem('stackapella.appearance');
        // Two cookies of one name: a path-scoped one stands in for the older host-only one (both readable here).
        document.cookie = 'sa_theme=' + encodeURIComponent(JSON.stringify({ skin: 'game', mode: 'dark', at: 100 })) + '; path=/tests';
        document.cookie = 'sa_theme=' + encodeURIComponent(JSON.stringify({ skin: 'serious', mode: 'dark', at: 900 })) + '; path=/';
        assert(document.cookie.split('; ').filter((c) => c.startsWith('sa_theme=')).length === 2, 'both cookies there');
        assert(theme.chosen().skin === 'serious', `the newest: ${JSON.stringify(theme.chosen())}`);
        document.cookie = 'sa_theme=' + encodeURIComponent(JSON.stringify({ skin: 'game', mode: 'dark', at: 2000 })) + '; path=/tests';
        assert(theme.chosen().skin === 'game', 'the newer one, whichever is listed first');
    } finally {
        document.cookie = 'sa_theme=; path=/tests; max-age=0';
        document.cookie = 'sa_theme=; path=/; max-age=0';
        if (kept === null) localStorage.removeItem('stackapella.appearance');
        else localStorage.setItem('stackapella.appearance', kept);
        theme.apply();
    }
});
