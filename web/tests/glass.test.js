// One glass for the app and the public pages (docs/glass-spec.md, section 5): css/glass.css's --gem-* properties are
// js/gem-spec.js's numbers, by the same names, none missing and none extra; the built sheets carry them (input.css
// @imports glass.css, and Tailwind inlines it); and the bubble lives in glass.css alone.
import { assert, test } from './harness.js';
import { GEM, gemProperties } from '/js/gem-spec.js';

const text = async (path) => (await fetch(path, { cache: 'no-store' })).text();

/** The --gem-* declarations between glass.css's gem-spec markers, as { name: value }. */
function declared(css) {
    const start = css.indexOf('/* gem-spec:start */');
    const end = css.indexOf('/* gem-spec:end */');
    assert(start >= 0 && end > start, 'glass.css marks its gem-spec block');
    const out = {};
    for (const [, name, value] of css.slice(start, end).matchAll(/(--gem-[\w-]+)\s*:\s*([^;]+);/g)) {
        assert(!(name in out), `glass.css declares ${name} once`);
        out[name] = value.trim();
    }
    return out;
}

test('glass: css/glass.css holds gem-spec.js\'s every number, by the same name, and nothing else', async () => {
    const css = declared(await text('/css/glass.css'));
    const spec = gemProperties();
    assert(Object.keys(spec).length > 60, `gem-spec.js names ${Object.keys(spec).length} numbers`);
    const missing = Object.keys(spec).filter((name) => !(name in css));
    const extra = Object.keys(css).filter((name) => !(name in spec));
    const differ = Object.keys(spec).filter((name) => name in css && css[name] !== spec[name]).map((name) => `${name}: css ${css[name]}, js ${spec[name]}`);
    assert(!missing.length, `glass.css lacks ${missing.join(', ')}`);
    assert(!extra.length, `glass.css has ${extra.join(', ')}, which gem-spec.js doesn't`);
    assert(!differ.length, `they differ: ${differ.join('; ')}`);
    assert(Object.isFrozen(GEM), 'GEM is frozen');
});

test('glass: the built sheets carry glass.css (input.css @imports it) and the bubble is written once, in glass.css', async () => {
    const input = await text('/css/input.css');
    assert(/^@import '\.\/glass\.css';$/m.test(input), 'input.css @imports ./glass.css');
    assert(!/^\.bubble \{/m.test(input), 'input.css no longer writes the bubble itself');
    assert(/^\.bubble \{/m.test(await text('/css/glass.css')), 'glass.css writes the bubble');
    for (const sheet of ['/css/app.css', '/css/site.css']) {
        const built = await text(sheet);
        assert(/--gem-radius:\s*\.?0?\.38/.test(built), `${sheet} carries the gem tokens`);
        assert(/\.bubble\{|\.bubble \{/.test(built), `${sheet} carries the bubble`);
    }
});

test('glass: the pages with a sheet of their own carry glass.css as it is (tool/glass_copy.py writes the copies)', async () => {
    const glass = await text('/css/glass.css');
    const source = glass.slice(glass.indexOf('/* gem-spec:start */')).trimEnd() + '\n';
    const start = '/* glass:start (tool/glass_copy.py writes this from css/glass.css; never edit it here) */\n';
    for (const name of ['landing.css', 'your-choir.css']) {
        const sheet = await text(`/css/${name}`);
        const a = sheet.indexOf(start);
        const b = sheet.indexOf('/* glass:end */');
        assert(a >= 0 && b > a, `${name} marks its glass copy`);
        assert(sheet.slice(a + start.length, b) === source, `${name}'s glass differs from glass.css: run python3 -I tool/glass_copy.py in web/`);
    }
});
