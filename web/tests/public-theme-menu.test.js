// The public Theme menu (js/public/theme-menu.js): opens and stays open while the look changes, every
// choice through theme-boot's choose(), its pressed states following <html>, and it closes as menus do.
import { currentLook, mountThemeMenus } from '/js/public/theme-menu.js';
import { assert, test } from './harness.js';

test('theme menu: light or dark and a palette go through choose(), the panel stays open, Escape closes it', () => {
    const root = document.documentElement;
    const before = { theme: root.dataset.theme, skin: root.dataset.skin };
    const real = window.stackapellaTheme;
    const calls = [];
    window.stackapellaTheme = { choose: (change) => { calls.push(change); if (change.colour) root.dataset.theme = `${change.colour}-${currentLook().mode}`; if (change.mode) root.dataset.theme = `${currentLook().colour}-${change.mode}`; } };
    const box = document.createElement('div');
    box.innerHTML = `<button type="button" data-theme-menu-button aria-expanded="false" aria-controls="tm-test">Theme</button>
        <div id="tm-test" data-theme-menu hidden><button type="button" data-theme-mode="light">Light</button><button type="button" data-theme-mode="dark">Dark</button>
        <button type="button" data-theme-colour="warm">Warm</button><button type="button" data-theme-colour="stage">Stage</button></div>`;
    document.body.append(box);
    try {
        root.dataset.theme = 'stage-dark';
        const [menu] = mountThemeMenus(box);
        const button = box.querySelector('[data-theme-menu-button]');
        const panel = box.querySelector('#tm-test');
        button.click();
        assert(!panel.hidden && button.getAttribute('aria-expanded') === 'true', 'open');
        assert(box.querySelector('[data-theme-colour=stage]').getAttribute('aria-pressed') === 'true', 'the palette in use is pressed');
        box.querySelector('[data-theme-colour=warm]').click();
        box.querySelector('[data-theme-mode=light]').click();
        assert(JSON.stringify(calls) === '[{"colour":"warm"},{"mode":"light"}]', JSON.stringify(calls));
        assert(!panel.hidden, 'stays open while the look changes');
        assert(box.querySelector('[data-theme-mode=light]').getAttribute('aria-pressed') === 'true' && box.querySelector('[data-theme-colour=warm]').getAttribute('aria-pressed') === 'true', 'pressed follows the look');
        panel.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        assert(panel.hidden && document.activeElement === button, 'Escape closes it and focus goes back');
        button.click();
        document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        assert(panel.hidden, 'a tap outside closes it');
        assert(mountThemeMenus(box).length === 0, 'mounted once');
        menu.destroy();
    } finally {
        box.remove();
        window.stackapellaTheme = real;
        root.dataset.theme = before.theme;
        root.dataset.skin = before.skin;
    }
});
