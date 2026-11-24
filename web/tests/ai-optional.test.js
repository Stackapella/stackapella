// AI optional (docs/ai-optional-plan.md): one aiOn() behind the ai_optional switch, and the device's return counts by
// choice and band (js/ai-counts.js).
import { assert, test } from './harness.js';
import { aiOn, AI_KEY, cardAllowed, noteAiArrival } from '../js/ai-choice.js';
import { aiReturnKinds, lessonBucket, weekOneLessons } from '../js/ai-counts.js';
import { account } from '../js/stores/account.js';
import { local } from '../js/storage.js';

test('ai optional: before the switch on for everyone; after it, the account\'s choice, else the device\'s key', () => {
    const was = { me: account.me, config: account.config, key: local.get(AI_KEY) };
    try {
        account.config = { aiOptional: false };
        account.me = { aiFeatures: false };
        assert(aiOn() === true, 'off before the switch');
        account.config = { aiOptional: true };
        assert(aiOn() === false, 'the account\'s off ignored');
        account.me = { aiFeatures: null };
        assert(aiOn() === false, 'not chosen should count as off (accounts from before were set on by the server)');
        account.me = { aiFeatures: true };
        assert(aiOn() === true, 'the account\'s on ignored');
        account.me = null;
        local.remove(AI_KEY);
        assert(aiOn() === false, 'a guest from / has AI');
        noteAiArrival('?ref=ai-harmony-singing', '');
        assert(aiOn() === true, 'a guest from /ai-harmony-singing has no AI');
        local.remove(AI_KEY);
        noteAiArrival('?ref=reddit-choir&for=ai', '');
        assert(aiOn() === true, 'for=ai beside another ref was ignored');
        local.remove(AI_KEY);
        noteAiArrival('', 'sa_theme=dark; sa_for=ai');
        assert(aiOn() === true, 'the site\'s sa_for=ai cookie was ignored');
    } finally {
        account.me = was.me;
        account.config = was.config;
        if (was.key === null) local.remove(AI_KEY);
        else local.set(AI_KEY, was.key);
    }
});

test('ai optional: the return counts land on their days, each once, with the choice and the band', () => {
    const one = aiReturnKinds({ state: 'on', band: '25-34', days: 1, lessons: 0, today: '2026-12-02' });
    assert(JSON.stringify(one.kinds) === JSON.stringify(['aic:back_d1:on:25-34', 'aic:back_w1:on:25-34']), one.kinds.join());
    const again = aiReturnKinds({ state: 'on', band: '25-34', days: 1, lessons: 0, kept: one.kept, today: '2026-12-02' });
    assert(again.kinds.length === 0, 'sent twice the same day');
    const three = aiReturnKinds({ state: 'on', band: '25-34', days: 3, lessons: 0, kept: one.kept, today: '2026-12-04' });
    assert(three.kinds.length === 0, 'week one sent twice');
    const seven = aiReturnKinds({ state: 'off', band: null, days: 7, lessons: 4, kept: three.kept, today: '2026-12-08' });
    assert(JSON.stringify(seven.kinds) === JSON.stringify(['aic:back_d7:off:unknown', 'aic:wk1_lessons:off:unknown:3-5']), seven.kinds.join());
    const guest = aiReturnKinds({ state: null, band: 'guest', days: 1, lessons: 0, today: '2026-12-02' });
    assert(guest.kinds[0] === 'aic:back_d1:unset:guest', guest.kinds.join());
    const w4 = aiReturnKinds({ state: 'off', band: '65+', days: 23, lessons: 4, kept: seven.kept, today: '2026-12-24' });
    assert(JSON.stringify(w4.kinds) === JSON.stringify(['aic:back_w4:off:65+']), w4.kinds.join());
    assert(['0', '1-2', '1-2', '3-5', '6+'].join() === [0, 1, 2, 5, 9].map(lessonBucket).join(), 'buckets');
    const start = Date.parse('2026-12-01T10:00:00Z');
    const progress = { lessons: { a: { stars: 2, at: start + 3600e3 }, b: { stars: 0, at: start + 7200e3 }, c: { stars: 1, at: start + 8 * 86400e3 } }, courses: { x: { lessons: { d: { stars: 3, at: start + 86400e3 } } } } };
    assert(weekOneLessons(progress, start) === 2, `week one lessons ${weekOneLessons(progress, start)}`);
});

test('ai optional: the card shows only when every rule holds', () => {
    const yes = { optional: true, choir: true, chosen: false, adult: true, songOk: true, firstPart: true, heard: true, firstDay: false, shows: 0, askFree: true };
    assert(cardAllowed(yes), 'all rules met, no card');
    assert(cardAllowed({ ...yes, adult: undefined }), 'a guest should see it');
    for (const [k, v] of [['optional', false], ['choir', false], ['chosen', true], ['adult', false], ['songOk', false], ['firstPart', false], ['heard', false], ['firstDay', true], ['shows', 2], ['askFree', false]]) {
        assert(!cardAllowed({ ...yes, [k]: v }), `shown with ${k} = ${v}`);
    }
    assert(cardAllowed({ ...yes, shows: 1 }), 'closed once should show once more');
});

test('ai optional: AI use is counted once a day each, only behind the switch', async () => {
    const { aiUse } = await import('../js/ai-choice.js');
    const was = { config: account.config, me: account.me, use: local.get('stackapella.aiUse') };
    try {
        local.remove('stackapella.aiUse');
        account.config = { aiOptional: false };
        aiUse('guide_heard', Date.parse('2026-12-02T10:00:00Z'));
        assert(local.get('stackapella.aiUse') === null, 'counted before the switch');
        account.config = { aiOptional: true };
        account.me = null;
        aiUse('guide_heard', Date.parse('2026-12-02T10:00:00Z'));
        const day = JSON.parse(local.get('stackapella.aiUse')).guide_heard;
        assert(/^\d{4}-\d{2}-\d{2}$/.test(day), `the day kept: ${day}`);
        aiUse('choir_start', Date.parse('2026-12-02T11:00:00Z'));
        const kept = JSON.parse(local.get('stackapella.aiUse'));
        assert(kept.guide_heard === day && kept.choir_start === day, JSON.stringify(kept));
    } finally {
        account.config = was.config;
        account.me = was.me;
        if (was.use === null) local.remove('stackapella.aiUse');
        else local.set('stackapella.aiUse', was.use);
    }
});
