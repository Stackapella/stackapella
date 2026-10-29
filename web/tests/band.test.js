// The backing band (js/band.js): each style's arrangement, and the synth when there are no samples.
import { bandFor, renderBand, styleOf } from '/js/band.js';
import { account } from '/js/stores/account.js';
import { assert, test } from './harness.js';

// C, F, G, C: a bar each in 4/4.
const CHORDS = [{ beat: 0, root: 0, quality: 'maj' }, { beat: 4, root: 5, quality: 'maj' }, { beat: 8, root: 7, quality: 'maj' }, { beat: 12, root: 0, quality: 'maj' }];

test('band: Gentle holds a chord and a root each bar; Pop strikes every beat with a kit; Swing walks', () => {
    const gentle = bandFor(CHORDS, { endBeat: 16, style: 'gentle' });
    assert(gentle.strikes.length === 4 && gentle.bass.length === 4, `gentle ${gentle.strikes.length} ${gentle.bass.length}`);
    assert(gentle.bass.map((n) => n.pitch % 12).join() === '0,5,7,0', gentle.bass.map((n) => n.pitch).join());
    assert(gentle.bass.every((n) => n.pitch >= 40 && n.pitch < 52), 'the bass sits from E2 up');
    const pop = bandFor(CHORDS, { endBeat: 16, style: 'pop' });
    assert(pop.strikes.length === 16 && pop.drums.some((d) => d.piece === 'kick') && pop.drums.some((d) => d.piece === 'snare'), 'pop');
    const swing = bandFor(CHORDS, { endBeat: 16, style: 'swing' });
    assert(swing.bass.length === 16, `four walking notes a bar: ${swing.bass.length}`);
    const into = swing.bass.find((n) => n.beat === 3).pitch;
    assert(Math.abs((into % 12) - 5) === 1 || Math.abs((into % 12) - 5) === 11, `beat 4 steps into F: ${into}`);
    assert(swing.drums.every((d) => d.piece.startsWith('brush')), 'brushes');
    assert(styleOf('pad') === 'gentle' && styleOf('swing') === 'swing', 'old sounds are Gentle');
});

test('band: with no samples the synth plays it, a loop exactly its length', async () => {
    const before = account.config;
    account.config = { ...(before ?? {}), instrumentsIndexUrl: null };
    try {
        const band = bandFor(CHORDS, { endBeat: 16, style: 'pop' });
        const out = await renderBand(band, { bpm: 120, sampleRate: 24000, endBeat: 16, style: 'pop', loop: true });
        assert(out.length === 16 * 0.5 * 24000, `a loop: ${out.length}`);
        const energy = out.reduce((sum, v) => sum + v * v, 0);
        assert(energy > 1, `it sounds: ${energy}`);
        const keysOnly = await renderBand(band, { bpm: 120, sampleRate: 24000, endBeat: 16, tracks: ['keys'] });
        assert(keysOnly.reduce((sum, v) => sum + v * v, 0) > 1, 'the keys alone');
    } finally {
        account.config = before;
    }
});
