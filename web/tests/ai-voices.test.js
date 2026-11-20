// AI voices (js/ai-voices.js, docs/ai-voices-build-plan.md): an AI guide is offered only while auth.config says the
// ai_voices switch is on, says so in words wherever it's credited, and never counts as a real voice.
import { aiCredit, allowed, isAi, voiceCredit } from '/js/ai-voices.js';
import { account } from '/js/stores/account.js';
import { aiPartsOf, catalogue, creditsFor, recordingFor, versionsFor } from '/js/stores/catalogue.js';
import { assert, test } from './harness.js';

const ai = (over = {}) => ({ id: 'Ai1', partKey: 'alto', url: 'https://cdn.example.com/a.m4a', sampleRate: 48000, frames: 1, offsetFrames: 0, credit: null, creditHandle: null, source: 'ai', aiBasedOn: null, ...over });
const real = { id: 'Re1', partKey: 'bass', url: 'https://cdn.example.com/b.m4a', sampleRate: 48000, frames: 1, offsetFrames: 0, credit: 'Sarah K.', creditHandle: null, source: 'recorded', aiBasedOn: null };
const song = { id: 'ai-test-song', version: 1, parts: [
    { key: 'alto', guide: ai({ aiBasedOn: 'Ada E.' }), versions: [ai({ aiBasedOn: 'Ada E.', default: true })], hasRealGuide: false },
    { key: 'bass', guide: real, versions: [{ ...real, default: true }], hasRealGuide: true },
] };

function withConfig(aiVoices, run) {
    const before = account.config;
    const list = catalogue.list;
    account.config = aiVoices === null ? null : { ...(before ?? {}), aiVoices };
    catalogue.list = [song];
    try {
        run();
    } finally {
        account.config = before;
        catalogue.list = list;
    }
}

test('ai voices: off (or not yet known), an AI guide is never offered and its part plays the practice voice', () => {
    for (const state of [false, null]) {
        withConfig(state, () => {
            assert(!allowed(ai()), `not allowed while the switch is ${state}`);
            assert(recordingFor(song.id, 'alto') === null && versionsFor(song.id, 'alto') === null, 'the part has no guide');
            assert(aiPartsOf(song.id).length === 0, 'no part is marked');
            assert(recordingFor(song.id, 'bass')?.recording.id === 'Re1', 'a real voice is untouched');
        });
    }
});

test('ai voices: on, the AI guide plays and its part is marked, never credited as a real voice', () => {
    withConfig(true, () => {
        assert(recordingFor(song.id, 'alto')?.recording.id === 'Ai1', 'the AI guide is the part’s voice');
        assert(versionsFor(song.id, 'alto')?.versions.length === 1, 'and its one version');
        assert(aiPartsOf(song.id).join() === 'alto', 'the alto is marked');
        assert(creditsFor(song.id).join() === 'Sarah K.', 'only the real singer is in the credits');
    });
});

test('ai voices: the words say AI, with the singer it’s based on when there is one', () => {
    assert(isAi(ai()) && !isAi(real) && isAi({ ai: true }), 'a recording, or a session voice entry');
    assert(/AI voice, waiting for a real singer/.test(aiCredit(null)), aiCredit(null));
    assert(/Ada E\.'s singing, with permission/.test(aiCredit('Ada E.')), aiCredit('Ada E.'));
    assert(voiceCredit(ai()) === aiCredit(null), 'an AI guide never reads as "Sung by one of our singers"');
    assert(/Sarah K\./.test(voiceCredit(real)), voiceCredit(real));
});
