// The admin's menu by job (Hillel, 2026-11-10): every page sits in a group that exists, once, with the old paths kept;
// and the feature switches never share Safety's page with the emergency controls.
import { GROUPS, SECTIONS } from '/js/admin/shell.js';
import { EMERGENCY } from '/js/admin/safety.js';
import { assert, test } from './harness.js';

test('admin nav: every page has a group that exists, and every group a page', () => {
    const keys = GROUPS.map((g) => g.key);
    assert(keys.join() === 'inbox,users,library,marketing,numbers,settings', keys.join());
    const homeless = SECTIONS.filter((s) => !keys.includes(s.group)).map((s) => s.name);
    assert(!homeless.length, `pages without a group: ${homeless.join()}`);
    const empty = keys.filter((k) => !SECTIONS.some((s) => s.group === k));
    assert(!empty.length, `groups without a page: ${empty.join()}`);
    const names = SECTIONS.map((s) => s.name);
    assert(new Set(names).size === names.length, 'each page once');
});

test('admin nav: the paths stay as they were, and Switches has its own under Settings', () => {
    const path = Object.fromEntries(SECTIONS.map((s) => [s.name, s.path]));
    for (const name of ['people', 'safety', 'links', 'singers', 'guides', 'growth', 'outreach', 'audit', 'code-watch', 'mudeo']) {
        assert(path[name] === `/admin/${name}`, `${name}: ${path[name]}`);
    }
    assert(path.home === '/admin', path.home);
    const switches = SECTIONS.find((s) => s.name === 'switches');
    assert(switches?.path === '/admin/switches' && switches.group === 'settings', JSON.stringify(switches));
    assert(SECTIONS.find((s) => s.name === 'safety').group === 'settings', 'Safety under Settings');
});

test('admin nav: Safety keeps the emergency controls only; the features are switches', () => {
    for (const name of ['shield_mode', 'pause_signups', 'read_only']) assert(EMERGENCY.includes(name), name);
    for (const name of ['indexnow', 'outreach_autosend', 'gallery', 'coaching', 'event_schedule', 'lesson_try', 'compare_pages', 'open_source']) {
        assert(!EMERGENCY.includes(name), `${name} is a feature switch`);
    }
    assert(EMERGENCY.every((name) => name === 'shield_mode' || name === 'read_only' || name.startsWith('pause_')), 'only pauses and lockdowns');
});
