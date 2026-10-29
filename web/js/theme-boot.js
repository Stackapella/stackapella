// The chosen theme, on the page before it paints (docs/decisions.md, "themes", "the theme follows
// you"): a plain script in <head> on every page (the app's, and the server's public pages, the help
// and guides included), so no page flashes the wrong colours. The choice ({ mode, colour, accent,
// at }) is kept in this address's localStorage and in the `sa_theme` cookie on the domain the server
// names (<meta name="theme-cookie-domain">: .stackapella.com, so the app and the public site share
// it; host-only without one), the later one winning. `choose()` is the one way to change it, on any
// page; signed in, the account's choice comes back through it (js/stores/appearance.js). Match my
// device follows the system as it changes. Sets
// <html data-theme="<colour>-<mode>"> (css/themes.css), data-accent (css/accents.css: a chosen accent,
// else the palette's own), data-skin ("game", the default, or "serious": docs/skins-plan.md; Hillel's
// "two skins for one app") and the browser bar's colour. Game mode has its own light or dark
// (`gameMode`, dark until chosen), so each skin keeps its own; in the app only (its page has
// <meta name="stackapella-app"> before this script): the public site's game look is dark only
// (Hillel: "the landing page with game mode on can always be dark mode, but the app should support
// both").
(function () {
    const app = !!document.querySelector('meta[name="stackapella-app"]');
    const COLOURS = {"warm-dark": "#17120f", "warm-light": "#faf5ee", "midnight-dark": "#0d1220", "midnight-light": "#f1f5fb", "graphite-dark": "#151617", "graphite-light": "#f3f3f1", "stage-dark": "#000000", "stage-light": "#f0f0f0"};
    const FAMILIES = ['stage', 'warm', 'midnight', 'graphite']; // Plum went: a choice of it shows as the mode's own
    const ACCENTS = ['coral', 'amber', 'green', 'teal', 'sky', 'indigo', 'violet', 'rose']; // tool/accents.py; else the palette's own
    // Each theme's surfaces [ink, panel, raised, mode], written by tool/accents.py, for a custom accent.
    /* surfaces:start */ const SURFACES = {"warm-dark": ["#17120f", "#1e1814", "#2a221d", "dark"], "warm-light": ["#faf5ee", "#ffffff", "#f1e9de", "light"], "midnight-dark": ["#0d1220", "#141b2d", "#1d263c", "dark"], "midnight-light": ["#f1f5fb", "#ffffff", "#e4ebf5", "light"], "graphite-dark": ["#151617", "#1d1e20", "#27292c", "dark"], "graphite-light": ["#f3f3f1", "#ffffff", "#e8e8e5", "light"], "stage-dark": ["#000000", "#0e0e0e", "#1c1c1c", "dark"], "stage-light": ["#f0f0f0", "#ffffff", "#e4e4e4", "light"]}; /* surfaces:end */

    // A custom accent (a colour the person picked), fitted to the theme as tool/accents.py fits the
    // palettes' own: kept if its words read 4.5:1 on every surface, else moved in OKLCH to the
    // nearest lightness that does, with a stronger shade and the colour of words on it. The same
    // steps as accents.py's fit_custom (tests/vectors/accent.json checks they agree).
    const NEED = 4.5;
    const hexRgb = (v) => [1, 3, 5].map((i) => parseInt(v.slice(i, i + 2), 16) / 255);
    const rgbHex = (rgb) => '#' + rgb.map((c) => Math.round(Math.max(0, Math.min(1, c)) * 255).toString(16).padStart(2, '0')).join('');
    const linear = (c) => (c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
    const gamma = (c) => (c <= 0.0031308 ? 12.92 * c : 1.055 * Math.pow(c, 1 / 2.4) - 0.055);
    const luminance = (rgb) => 0.2126 * linear(rgb[0]) + 0.7152 * linear(rgb[1]) + 0.0722 * linear(rgb[2]);
    const contrast = (a, b) => {
        const [x, y] = [luminance(a), luminance(b)].sort((p, q) => q - p);
        return (x + 0.05) / (y + 0.05);
    };
    function oklchRgb(l, c, h) {
        const a = c * Math.cos((h * Math.PI) / 180);
        const b = c * Math.sin((h * Math.PI) / 180);
        const l3 = Math.pow(l + 0.3963377774 * a + 0.2158037573 * b, 3);
        const m3 = Math.pow(l - 0.1055613458 * a - 0.0638541728 * b, 3);
        const s3 = Math.pow(l - 0.0894841775 * a - 1.291485548 * b, 3);
        const lin = [4.0767416621 * l3 - 3.3077115913 * m3 + 0.2309699292 * s3, -1.2684380046 * l3 + 2.6097574011 * m3 - 0.3413193965 * s3, -0.0041960863 * l3 - 0.7034186147 * m3 + 1.707614701 * s3];
        if (lin.some((v) => v < -1e-4 || v > 1 + 1e-4)) return null;
        return lin.map((v) => gamma(Math.max(0, Math.min(1, v))));
    }
    function colourAt(l, c, h) {
        while (c > 0) {
            const rgb = oklchRgb(l, c, h);
            if (rgb) return rgb;
            c -= 0.005;
        }
        return oklchRgb(l, 0, h);
    }
    const passes = (rgb, surfaces) => Math.min(...surfaces.map((s) => contrast(rgb, s))) >= NEED;
    function toOklch(rgb) {
        const [r, g, b] = rgb.map(linear);
        const l_ = Math.pow(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b, 1 / 3);
        const m_ = Math.pow(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b, 1 / 3);
        const s_ = Math.pow(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b, 1 / 3);
        const l = 0.2104542553 * l_ + 0.793617785 * m_ - 0.0040720468 * s_;
        const a = 1.9779984951 * l_ - 2.428592205 * m_ + 0.4505937099 * s_;
        const b2 = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.808675766 * s_;
        return [l, Math.hypot(a, b2), ((((Math.atan2(b2, a) * 180) / Math.PI) % 360) + 360) % 360];
    }
    function variants(l, c, h, dark, ink) {
        const strong = colourAt(dark ? Math.min(0.97, l + 0.08) : Math.max(0.2, l - 0.08), c, h);
        const rgb = colourAt(l, c, h);
        const words = contrast(ink, rgb) >= NEED ? ink : contrast([0, 0, 0], rgb) >= contrast([1, 1, 1], rgb) ? [0, 0, 0] : [1, 1, 1];
        return [rgb, strong, words];
    }
    /** `hex` fitted to theme `id`: { accent, strong, ink, adjusted }, or null for an unknown theme or colour. */
    function fitAccent(hex, id) {
        const theme = SURFACES[id];
        if (!theme || !/^#[0-9a-f]{6}$/i.test(hex || '')) return null;
        const dark = theme[3] === 'dark';
        const ink = hexRgb(theme[0]);
        const surfaces = theme.slice(0, 3).map(hexRgb);
        const picked = hexRgb(hex.toLowerCase());
        const [l, c, h] = toOklch(picked);
        if (passes(picked, surfaces)) {
            const [, strong, words] = variants(l, c, h, dark, ink);
            return { accent: rgbHex(picked), strong: rgbHex(strong), ink: rgbHex(words), adjusted: false };
        }
        const steps = dark ? Array.from({ length: 51 }, (_, i) => 0.74 + i * 0.005) : Array.from({ length: 81 }, (_, i) => 0.6 - i * 0.005);
        const fitted = steps.find((step) => passes(colourAt(step, c, h), surfaces)) ?? (dark ? 0.99 : 0.2);
        const [rgb, strong, words] = variants(fitted, c, h, dark, ink);
        return { accent: rgbHex(rgb), strong: rgbHex(strong), ink: rgbHex(words), adjusted: true };
    }
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    // The choice: this address's own (localStorage), or the `sa_theme` cookie (on .stackapella.com,
    // so the app and the public site look the same) when that one was made later, on the other host.
    function chosen() {
        let own = {};
        let shared = {};
        try {
            own = JSON.parse(window.localStorage.getItem('stackapella.appearance') || 'null') || {};
        } catch (error) {
            own = {}; // storage blocked: the cookie, or today's look
        }
        try {
            const cookie = document.cookie.split('; ').find((c) => c.startsWith('sa_theme='));
            shared = cookie ? JSON.parse(decodeURIComponent(cookie.slice(9))) || {} : {};
        } catch (error) {
            shared = {}; // unreadable: this address's own
        }
        return (shared.at || 0) > (own.at || 0) ? shared : own;
    }
    function apply() {
        const choice = chosen();
        // Not chosen yet: dark, as the app has always looked (Hillel's default until he picks). Game mode
        // turns dark by itself and keeps its own choice (Hillel: "auto set to dark but show toggle to
        // return to light"); serious mode's is kept apart, so each skin comes back as it was left.
        const game = choice.skin !== 'serious';
        const wanted = game ? (app ? choice.gameMode || 'dark' : 'dark') : choice.mode;
        const mode = wanted === 'light' || wanted === 'dark' ? wanted : wanted === 'system' ? (system.matches ? 'dark' : 'light') : 'dark';
        // A colour not chosen is the mode's own: Stage in dark, Warm in light (docs/decisions.md).
        const colour = FAMILIES.includes(choice.colour) ? choice.colour : mode === 'dark' ? 'stage' : 'warm';
        const id = colour + '-' + mode;
        document.documentElement.dataset.theme = id;
        const root = document.documentElement;
        const custom = choice.accent === 'custom' ? fitAccent(choice.customAccent, id) : null;
        for (const name of ['accent', 'accent-strong', 'accent-ink']) root.style.removeProperty('--color-' + name);
        if (custom) {
            // A colour the person picked: fitted to this theme, set on the page itself.
            root.dataset.accent = 'custom';
            root.style.setProperty('--color-accent', custom.accent);
            root.style.setProperty('--color-accent-strong', custom.strong);
            root.style.setProperty('--color-accent-ink', custom.ink);
        } else if (ACCENTS.includes(choice.accent)) root.dataset.accent = choice.accent;
        else delete root.dataset.accent;
        root.dataset.skin = game ? 'game' : 'serious';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta && COLOURS[id]) meta.setAttribute('content', COLOURS[id]);
        // An installed app on iPhone: dark status bar text on a light theme.
        const bar = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
        if (bar) bar.setAttribute('content', mode === 'light' ? 'default' : 'black-translucent');
        document.dispatchEvent(new CustomEvent('stackapella:theme', { detail: id }));
        return id;
    }
    /**
     * Chooses (any of `mode`, `gameMode`, `colour`, `accent`, `skin`; the rest as they are): kept here and in the shared
     * cookie for a year, then shown. Returns the whole choice.
     */
    function choose(change) {
        const before = chosen();
        const pick = (name) => (change && name in change ? change[name] : before[name]) ?? null;
        const choice = { mode: pick('mode') ?? 'dark', gameMode: pick('gameMode'), colour: pick('colour'), accent: pick('accent'), customAccent: pick('customAccent'), skin: pick('skin') === 'serious' ? 'serious' : 'game', at: Date.now() };
        // A light or dark chosen in the app's game mode is game mode's own: serious mode's stays as it was.
        if (app && choice.skin === 'game' && change && 'mode' in change && !('gameMode' in change)) {
            choice.gameMode = change.mode;
            choice.mode = before.mode ?? 'dark';
        }
        try {
            window.localStorage.setItem('stackapella.appearance', JSON.stringify(choice));
        } catch (error) {
            // Storage blocked: the cookie still carries it.
        }
        const domain = document.querySelector('meta[name="theme-cookie-domain"]')?.getAttribute('content');
        document.cookie = 'sa_theme=' + encodeURIComponent(JSON.stringify(choice)) + '; path=/; max-age=31536000; samesite=lax' + (domain ? '; domain=' + domain : '') + (location.protocol === 'https:' ? '; secure' : '');
        apply();
        return choice;
    }
    apply();
    system.addEventListener('change', apply);
    window.stackapellaTheme = { apply, chosen, choose, fitAccent };
})();
