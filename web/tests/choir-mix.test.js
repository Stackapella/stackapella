// Every choir's default mix (the coordinator, 2026-11-22: "vary the balance ... between your voice dupes"): each part a
// little louder or softer, seeded by the song so it's the same every play, within ±1.5 dB; a small stereo spread (S a
// little left, A right, T left of centre, B right of it); the tune never quieter than another part; a level Plus set wins.
import { choirMix, seededUnit } from '/js/own-choir-player.js';
import { assert, test } from './harness.js';

const voices = (seed) => ['soprano', 'alto', 'tenor', 'bass'].map((partKey) => ({ partKey, seed }));

test('choir mix: seeded, within 1.5 dB, the same each time', () => {
    const a = choirMix(voices('Rq5Sn1'), null, { tune: 'soprano' });
    const b = choirMix(voices('Rq5Sn1'), null, { tune: 'soprano' });
    assert(JSON.stringify(a) === JSON.stringify(b), 'the same song mixes the same');
    for (const { db } of Object.values(a)) assert(Math.abs(db) <= 1.5, `a level past 1.5 dB: ${db}`);
    const c = choirMix(voices('Other1'), null, { tune: 'soprano' });
    assert(JSON.stringify(a) !== JSON.stringify(c), 'another song varies differently');
    assert(seededUnit('x') >= -1 && seededUnit('x') <= 1);
});

test('choir mix: the spread, the tune clear and nearer the middle', () => {
    const mix = choirMix(voices('Rq5Sn1'), null, { tune: 'alto' });
    assert(mix.soprano.pan < 0 && mix.tenor.pan < 0 && mix.bass.pan > 0 && mix.alto.pan > 0, JSON.stringify(mix));
    assert(Math.abs(mix.tenor.pan) < Math.abs(mix.soprano.pan), 'the tenor nearer the middle than the soprano');
    assert(mix.alto.pan === 0.15, `the tune halfway to the middle: ${mix.alto.pan}`);
    for (const key of ['soprano', 'tenor', 'bass']) assert(mix.alto.db >= mix[key].db, `${key} louder than the tune`);
});

test('choir mix: a level set wins; the lead take sits where the tune does', () => {
    const sound = { balance: [{ partKey: 'bass', db: -4 }, { partKey: 'alto', db: 0 }] };
    const mix = choirMix([...voices('Rq5Sn1'), { partKey: 'lead-take', seed: 'Rq5Sn1' }], sound, { tune: 'soprano' });
    assert(mix.bass.db === -4, 'Plus set the bass');
    assert(mix['lead-take'].pan === mix.soprano.pan && mix['lead-take'].db === mix.soprano.db, 'the lead is the tune');
});
