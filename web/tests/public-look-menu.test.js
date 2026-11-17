// Language and appearance (js/public/look-menu.js): one pill whose menu holds the languages and Light, Dark or Auto;
// each skin's own light or dark through theme-boot's choose(), the menu open while the look changes, the arrows, Tab
// kept inside, Escape back to the pill, and the pill's name saying the current values.
import { currentMode, lookLabel, modeChange, mountLookMenus } from '/js/public/look-menu.js';
import { assert, test } from './harness.js';

const same = (actual, expected, what = '') => assert(actual === expected, `${what ? what + ': ' : ''}expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
const frame = () => new Promise((done) => requestAnimationFrame(() => setTimeout(done, 0)));
const key = (el, k, shiftKey = false) => el.dispatchEvent(new KeyboardEvent('keydown', { key: k, shiftKey, bubbles: true, cancelable: true }));

test('look menu: each skin keeps its own light or dark, Auto is the device, and the name says the values', () => {
    const root = document.createElement('div');
    root.dataset.skin = 'serious';
    same(currentMode({ chosen: () => ({}) }, root), 'dark', 'dark until chosen');
    same(currentMode({ chosen: () => ({ mode: 'light', gameMode: 'dark' }) }, root), 'light', 'calm reads mode');
    same(currentMode({ chosen: () => ({ mode: 'system' }) }, root), 'system', 'Auto');
    same(JSON.stringify(modeChange('system', root)), '{"mode":"system"}');
    root.dataset.skin = 'game';
    same(currentMode({ chosen: () => ({ mode: 'light', gameMode: null }) }, root), 'dark', 'game mode is dark until chosen');
    same(JSON.stringify(modeChange('light', root)), '{"gameMode":"light"}', 'game mode keeps its own');
    same(lookLabel('Language and appearance', ['English', 'Dark']), 'Language and appearance: English, Dark');
    same(lookLabel('Language and appearance', ['', 'Auto']), 'Language and appearance: Auto', 'one language: the look alone');
});

test('look menu: opens on the current choice, arrows and Tab stay inside, a look keeps it open, Escape gives focus back', async () => {
    const html = document.documentElement;
    const before = { theme: html.dataset.theme, skin: html.dataset.skin };
    const real = window.stackapellaTheme;
    let choice = { mode: 'dark' };
    const calls = [];
    window.stackapellaTheme = { chosen: () => choice, choose: (change) => { calls.push(change); choice = { ...choice, ...change }; } };
    const box = document.createElement('div');
    box.innerHTML = `<details data-look-menu><summary data-look-name="Language and appearance">EN</summary><div class="s-look-panel">
        <div data-lang-menu><ul><li><a href="#en" data-lang-choice="en" aria-current="true">English</a></li><li><a href="#es" data-lang-choice="es">Español</a></li></ul></div>
        <div><button type="button" data-look-mode="light">Light</button><button type="button" data-look-mode="dark">Dark</button><button type="button" data-look-mode="system">Auto</button></div></div></details>`;
    document.body.append(box);
    try {
        html.dataset.skin = 'serious';
        const [menu] = mountLookMenus(box);
        const details = box.querySelector('details');
        const pill = box.querySelector('summary');
        const [en, es] = box.querySelectorAll('[data-lang-choice]');
        const [light, dark, auto] = box.querySelectorAll('[data-look-mode]');
        same(pill.getAttribute('aria-label'), 'Language and appearance: English, Dark');
        same(pill.getAttribute('aria-expanded'), 'false');
        pill.focus();
        key(pill, 'ArrowDown');
        await frame();
        assert(details.open && pill.getAttribute('aria-expanded') === 'true', 'ArrowDown on the pill opens it');
        same(document.activeElement, en, 'focus on the current language');
        key(en, 'ArrowDown');
        same(document.activeElement, es, 'the arrows move');
        key(es, 'End');
        same(document.activeElement, auto, 'End is the last');
        key(auto, 'Tab');
        same(document.activeElement, en, 'Tab wraps inside');
        key(en, 'Tab', true);
        same(document.activeElement, auto, 'Shift+Tab wraps back');
        auto.click();
        await frame();
        same(JSON.stringify(calls), '[{"mode":"system"}]', 'calm: mode');
        assert(details.open, 'stays open while the look changes');
        assert(auto.getAttribute('aria-pressed') === 'true' && dark.getAttribute('aria-pressed') === 'false', 'pressed follows');
        same(pill.getAttribute('aria-label'), 'Language and appearance: English, Auto');
        html.dataset.skin = 'game';
        light.click();
        same(JSON.stringify(calls[1]), '{"gameMode":"light"}', 'game mode: its own');
        key(light, 'Escape');
        await frame();
        assert(!details.open && document.activeElement === pill, 'Escape closes it and focus goes back');
        details.open = true;
        await frame();
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        await frame();
        assert(!details.open, 'a tap outside closes it');
        same(mountLookMenus(box).length, 0, 'mounted once');
        menu.destroy();
    } finally {
        box.remove();
        window.stackapellaTheme = real;
        html.dataset.theme = before.theme;
        if (before.skin === undefined) delete html.dataset.skin;
        else html.dataset.skin = before.skin;
    }
});
