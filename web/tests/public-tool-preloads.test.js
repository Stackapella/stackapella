// Each tool page's module preloads (js/public/tools/preloads.json, written by tool/tool_preloads.py): every tool
// module's static imports, crawled here from the files themselves, must all be listed, so a tool page fetches its whole
// graph beside the stylesheet and never waits a round trip a level (docs/performance.md, 2026-11-03).
import { assert, test } from './harness.js';

const IMPORT = /^\s*(?:import|export)\s[^'"]*?\sfrom\s+['"]([^'"]+)['"]|^\s*import\s+['"]([^'"]+)['"]/gm;

async function crawl(entry) {
    const seen = new Set();
    const todo = [entry];
    while (todo.length) {
        const path = todo.shift();
        if (seen.has(path)) continue;
        seen.add(path);
        const source = (await (await fetch(path)).text()).replace(/\/\*[\s\S]*?\*\//g, '');
        for (const [, a, b] of source.matchAll(IMPORT)) {
            const spec = a || b;
            if (spec.startsWith('.')) todo.push(new URL(spec, location.origin + path).pathname);
        }
    }
    return seen;
}

test('tool preloads: every tool module\'s whole static graph is listed (run tool/tool_preloads.py after changing an import)', async () => {
    const lists = await (await fetch('/js/public/tools/preloads.json', { cache: 'no-store' })).json();
    assert(Object.keys(lists).includes('/js/public/tools/tuner.js'), 'the tuner is listed');
    for (const [module, listed] of Object.entries(lists)) {
        const found = await crawl(module);
        const missing = [...found].filter((p) => !listed.includes(p));
        const extra = listed.filter((p) => !found.has(p));
        assert(!missing.length && !extra.length, `${module}: missing ${missing.join(', ') || 'none'}; not imported ${extra.join(', ') || 'none'}`);
    }
});
