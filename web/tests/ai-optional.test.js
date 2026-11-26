// AI optional (docs/ai-optional-plan.md): one aiOn() behind the ai_optional switch, and the device's return counts by
// choice and band (js/ai-counts.js).
import { assert, test } from './harness.js';
import { aiChosen, aiHideOffered, aiOn, AI_KEY, hideAi, noteAiArrival, noteFirstShown } from '../js/ai-choice.js';
import { aiReturnKinds, lessonBucket, weekOneLessons } from '../js/ai-counts.js';
import { account } from '../js/stores/account.js';
import { local } from '../js/storage.js';

test('ai optional: before the switch on for everyone; after it, the account\'s choice (undecided: on), else the device\'s key', () => {
    const was = { me: account.me, config: account.config, key: local.get(AI_KEY) };
    try {
        account.config = { aiOptional: false };
        account.me = { aiFeatures: false };
        assert(aiOn() === true, 'off before the switch');
        account.config = { aiOptional: true };
        assert(aiOn() === false, 'the account\'s off ignored');
        // Hillel, 2026-11-26: "most new users to see it but have the option to hide forever".
        account.me = { aiFeatures: null, ageBand: '25-34' };
        assert(aiOn() === true, 'undecided should count as on');
        account.me = { aiFeatures: true, aiFeaturesSource: 'existing', ageBand: '35-44' };
        assert(aiOn() === true && !aiChosen(), 'an older account is on, and still undecided');
        account.me = { aiFeatures: null, ageBand: 'u18' };
        assert(aiOn() === false, 'an undecided account under 18 has AI');
        account.me = { aiFeatures: true, ageBand: 'u18', aiFeaturesSource: 'settings' };
        assert(aiOn() === false, 'an account under 18 has AI (the server never gives it them)');
        account.me = { aiFeatures: true };
        assert(aiOn() === true, 'the account\'s on ignored');
        account.me = null;
        local.remove(AI_KEY);
        assert(aiOn() === true, 'a guest from / should see AI until they hide it');
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

test('ai optional: Not for me is offered only on AI\'s first showing to someone undecided, and sticks', async () => {
    const was = { me: account.me, config: account.config, key: local.get(AI_KEY), from: local.get('stackapella.aiFrom'), first: local.get('stackapella.aiFirstShown') };
    try {
        account.config = { aiOptional: true };
        account.me = null;
        local.remove(AI_KEY);
        local.remove('stackapella.aiFirstShown');
        assert(aiHideOffered(), 'an undecided guest should be offered Hide on the first showing');
        noteFirstShown();
        assert(!aiHideOffered(), 'offered again after the first showing (Hillel: after that, only in Settings)');
        account.me = { id: 7, aiFeatures: null, ageBand: '25-34' };
        assert(aiHideOffered(), 'an account\'s own first showing should be theirs, not the device guest\'s');
        account.me = null;
        await hideAi('home_choir');
        assert(aiOn() === false && aiChosen() && !aiHideOffered(), 'a guest\'s hide did not stick');
        assert(local.get('stackapella.aiFrom') === 'hide', 'the device keeps where it came from, for the account they make');
        account.config = { aiOptional: false };
        assert(!aiHideOffered(), 'offered before the switch');
        account.config = { aiOptional: true };
        account.me = { aiFeatures: true, aiFeaturesSource: 'settings' };
        assert(!aiHideOffered(), 'offered to someone who chose on');
        account.me = { aiFeatures: null, ageBand: 'u18' };
        assert(!aiHideOffered(), 'offered where AI is already off');
    } finally {
        account.me = was.me;
        account.config = was.config;
        if (was.key === null) local.remove(AI_KEY);
        else local.set(AI_KEY, was.key);
        if (was.from === null) local.remove('stackapella.aiFrom');
        else local.set('stackapella.aiFrom', was.from);
        if (was.first === null) local.remove('stackapella.aiFirstShown');
        else local.set('stackapella.aiFirstShown', was.first);
    }
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
