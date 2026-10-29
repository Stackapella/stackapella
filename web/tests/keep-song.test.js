// Keep the finale (js/keep-song.js): a finale is kept only when every take it names was sung; its
// tracks are the takes as sung, then the partner lines and chords rendered at the takes' rate, named
// as the lesson says.
import { canKeep, finaleTracks } from '/js/keep-song.js';
import { assert, test } from './harness.js';

const RATE = 48000;
const take = (seconds) => ({ samples: new Float32Array(Math.round(seconds * RATE)).fill(0.1), sampleRate: RATE });
const lesson = {
    id: 'cp-x',
    keepSong: { title: { en: 'My counterpoint' }, takes: [{ step: 1, name: { en: 'My line' } }], from: 1, partners: [{ en: 'Cantus' }], chords: { en: 'Chords' } },
    steps: [
        { kind: 'learn' },
        { kind: 'play', exercise: { tempo: 60, turn: false, notes: [{ beat: 0, length: 4, step: 0 }], with: [{ notes: [{ beat: 0, length: 4, step: -12 }] }], chords: [{ beat: 0, root: 0, quality: 'maj', bass: null }] } },
    ],
};

test('keep the finale: only when every named take was sung this visit', () => {
    assert(!canKeep(lesson, { takes: {} }), 'nothing sung');
    assert(canKeep(lesson, { takes: { 1: take(4) } }), 'the take is there');
    assert(!canKeep({ ...lesson, keepSong: undefined }, { takes: { 1: take(4) } }), 'a lesson that does not opt in');
});

test('keep the finale: the take, then each partner line and the chords, at the take\'s rate', () => {
    const tracks = finaleTracks(lesson, { takes: { 1: take(4) } }, 60);
    assert(tracks.map((t) => t.name).join() === 'My line,Cantus,Chords', tracks.map((t) => t.name).join());
    assert(tracks.every((t) => t.sampleRate === RATE), 'one rate');
    assert(tracks[0].samples.length === 4 * RATE && tracks[0].volume === 1 && tracks[0].sung, 'the take as sung');
    assert(!tracks[1].sung && !tracks[2].sung, 'the lesson\'s own voices are not yours');
    assert(tracks[1].samples.length >= 4 * RATE && tracks[1].volume === 0.5, 'the partner, rendered from beat 0, 6 dB under');
    assert(tracks[2].samples.length > 0 && tracks[2].volume === 0.8, 'the chords');
    const plain = finaleTracks({ ...lesson, keepSong: { ...lesson.keepSong, chords: undefined } }, { takes: { 1: take(4) } }, 60);
    assert(plain.length === 2, 'no chords track unless named');
});
