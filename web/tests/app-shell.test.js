// The app's shell (index.html) before the app draws: its logo is the header's own mark (js/logo-mark.js, written by
// tool/icons.py), so a new mark can't leave the old one in the shell.
import { LOGO_MARK } from '/js/logo-mark.js';
import { assert, test } from './harness.js';

test('app shell: the logo shown before the app draws is the header\'s own mark, and hidden from screen readers', async () => {
    const html = await (await fetch('/index.html')).text();
    const splash = html.match(/<div class="app-splash" aria-hidden="true">(<svg[\s\S]*?<\/svg>)<\/div>/)?.[1];
    assert(splash, 'the shell has its logo');
    assert(splash.replace(/ width='\d+' height='\d+'/, '') === LOGO_MARK, 'the shell\'s mark is js/logo-mark.js\'s');
});
