// The service worker serves the app shell for every app address, so a screen opens offline. Its
// APP_ROUTE must cover every route the router knows (5d's offline flow found /groups and /contact
// left out); a new route without a sample here fails this test.
import { assert, test } from './harness.js';

const SAMPLES = {
    sing: '/p/abc/sing', studio: '/p/abc', admin: '/admin/singers', styleguide: '/styleguide', auth: '/signin/link',
    settings: '/settings', storage: '/storage', voice: '/voice', workout: '/workout', plus: '/plus', reminders: '/settings/reminders',
    course: '/learn', learn: '/learn/match-a-note', plusCourse: '/learn/c/vibrato', beta: '/beta/abc', create: '/create/abc', template: '/t/abc', join: '/join/abc',
    early: '/early/abc', invite: '/invite/abc', groups: '/groups', group: '/groups/abc', choir: '/choir/abc', challenge: '/challenge/abc', moved: '/moved',
    contact: '/contact', ideas: '/ideas', fresh: '/start-fresh', idea: '/ideas/Qx7Lm2Pa9Kd4', projects: '/',
};

test('sw: the app shell covers every route the router knows', async () => {
    const router = await (await fetch('/js/router.js')).text();
    const names = [...router.matchAll(/\{ name: '([\w-]+)', pattern:/g)].map((m) => m[1]);
    const sw = await (await fetch('/sw.js')).text();
    const source = sw.match(/const APP_ROUTE = \/(.+)\/;\n/)[1];
    const appRoute = new RegExp(source);
    assert(names.length > 20, `${names.length} routes read`);
    for (const name of names) {
        assert(SAMPLES[name], `the route ${name} has no sample address here`);
        assert(appRoute.test(SAMPLES[name]), `sw.js's APP_ROUTE misses ${name} (${SAMPLES[name]})`);
    }
});
