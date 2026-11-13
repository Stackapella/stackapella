// Send diagnostics' sound log (js/sound-log.js): a line is the time, the screen with its token out and what failed;
// the same failure twice is one line; at most 20 kept; and only errors from the sound code count as sound problems.
import { fromSound, lineFor, MAX, noteSound, screenOf, soundLog } from '/js/sound-log.js';
import { session } from '/js/storage.js';
import { assert, test } from './harness.js';

test('sound log: a screen as it goes in a line, tokens and songs as their pattern', () => {
    assert(screenOf('/learn/1-match?x=1#y') === '/learn/1-match', screenOf('/learn/1-match?x=1#y'));
    assert(screenOf('/choir/abc123') === '/choir/:token', screenOf('/choir/abc123'));
    assert(screenOf('/challenge/demo-token') === '/challenge/:token', screenOf('/challenge/demo-token'));
    assert(screenOf('/verify') === '/verify' || screenOf('/verify').endsWith(':token'), screenOf('/verify'));
    assert(screenOf('/p/1b66f0fb-528b/sing') === '/p/:song/sing', screenOf('/p/1b66f0fb-528b/sing'));
});

test('sound log: a line is the time, the screen and what failed, never longer than the server takes', () => {
    const at = new Date('2026-11-13T14:02:31Z');
    const error = { name: 'NotAllowedError', message: 'Permission denied' };
    assert(lineFor('mic', error, { at, path: '/learn/1-match' }) === '14:02:31 /learn/1-match mic: NotAllowedError Permission denied', lineFor('mic', error, { at, path: '/learn/1-match' }));
    assert(lineFor('stopped (audio)', null, { at, path: '/' }) === '14:02:31 / stopped (audio)');
    assert(lineFor('error', { name: 'Error', message: 'x'.repeat(900) }, { at, path: '/' }).length <= 300, 'kept short');
});

test('sound log: the same failure again is one line, and only the last 20 are kept', () => {
    const before = session.get('stackapella.soundLog');
    try {
        session.remove('stackapella.soundLog');
        noteSound('mic', { name: 'NotReadableError', message: 'busy' });
        noteSound('mic', { name: 'NotReadableError', message: 'busy' });
        assert(soundLog().length === 1, JSON.stringify(soundLog()));
        for (let i = 0; i < 30; i++) noteSound(`stopped (${i})`);
        assert(soundLog().length === MAX && MAX === 20, `${soundLog().length}`);
        assert(soundLog().at(-1).endsWith('stopped (29)'), soundLog().at(-1));
    } finally {
        if (before === null) session.remove('stackapella.soundLog');
        else session.set('stackapella.soundLog', before);
    }
});

test('sound log: an uncaught error counts only when it comes from the sound code', () => {
    assert(fromSound({ message: 'boom', stack: 'at Engine.toBuffer (http://x/engine/engine.js:401:24)' }), 'the engine');
    assert(fromSound({ name: 'NotSupportedError', message: "Failed to construct 'AudioBuffer'", stack: '' }), 'an AudioBuffer');
    assert(fromSound({ message: 'x', stack: 'at openMic (http://x/js/audio.js:127:9)' }), 'audio.js');
    assert(!fromSound({ message: 'Cannot read properties of null', stack: 'at render (http://x/js/views/settings.js:9:1)' }), 'a screen of ours');
});
