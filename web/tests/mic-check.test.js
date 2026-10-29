// The mic check's maths (js/mic-check.js): the meter's fill and its voice line, a voice heard only
// over the line (silence moves nothing on), "flat" after a while of nothing, and the memory per mic
// that asks again when the mics change.
import { checked, checkState, FLAT_SECONDS, HEARD_SECONDS, meterLevel, micsSignature, needsCheck, stepVoice, VOICE_DBFS, VOICE_LINE, voiceWatch } from '/js/mic-check.js';
import { assert, test } from './harness.js';

const run = (db, seconds, state = voiceWatch(), dt = 1 / 60) => {
    for (let t = 0; t < seconds; t += dt) state = stepVoice(state, db, dt);
    return state;
};

test('mic check: the meter fills with the level, and the voice line sits inside it', () => {
    assert(meterLevel(-100) === 0 && meterLevel(0) === 1, 'clamped at both ends');
    assert(meterLevel(null) === 0 && meterLevel(-Infinity) === 0, 'nothing heard is empty');
    assert(VOICE_LINE > 0.2 && VOICE_LINE < 0.6, `the line is in the meter's middle stretch (${VOICE_LINE})`);
    assert(meterLevel(VOICE_DBFS + 6) > VOICE_LINE, 'a voice fills past the line');
});

test('mic check: silence is ignored, a voice is heard, and a dip does not flicker', () => {
    const quiet = run(-65, 3);
    assert(!quiet.hearing && quiet.voiced === 0 && checkState(quiet) === 'listening', 'a quiet room hears nothing');
    const voice = run(-30, HEARD_SECONDS + 0.1, quiet);
    assert(voice.hearing && checkState(voice) === 'ok', 'a voice over the line is "Got you"');
    const dip = run(-60, 0.1, voice);
    assert(dip.hearing, 'a short dip under the line still shows "Hearing you"');
    assert(!run(-60, 0.5, voice).hearing, 'a longer one does not');
    assert(checkState(run(-30, 0.1)) === 'listening', 'a cough is not yet a voice');
});

test('mic check: nothing over the line for a while suggests another mic', () => {
    assert(checkState(run(-80, FLAT_SECONDS - 0.5)) === 'listening', 'not yet');
    assert(checkState(run(-80, FLAT_SECONDS + 0.2)) === 'flat', 'flat after a while of nothing');
    assert(checkState(run(-58, FLAT_SECONDS + 0.2)) === 'quiet', 'something under the line for a while: quiet (turn the gain up), not flat');
    assert(checkState(run(-30, 0.2, run(-80, FLAT_SECONDS + 0.2))) === 'listening', 'once a voice starts, it is no longer flat');
});

test('mic check: remembered per mic, and asked again when the mics change', () => {
    const mics = [{ deviceId: 'b' }, { deviceId: 'a' }];
    assert(micsSignature(mics) === micsSignature([...mics].reverse()), 'the order of the list does not matter');
    assert(needsCheck(null, 'a', mics), 'never checked: check');
    const kept = checked(null, 'a', mics);
    assert(!needsCheck(kept, 'a', mics), 'heard here: no check');
    assert(needsCheck(kept, 'b', mics), 'another mic: check it');
    const both = checked(kept, 'b', mics);
    assert(!needsCheck(both, 'a', mics) && !needsCheck(both, 'b', mics), 'both remembered');
    const plugged = [...mics, { deviceId: 'headset' }];
    assert(needsCheck(both, 'a', plugged), 'a headset plugged in: check again');
    assert(checked(both, 'a', plugged).ok.length === 1, 'and the list starts again');
    assert(needsCheck(both, 'a', [{ deviceId: 'a' }]), 'one unplugged: check again');
});
