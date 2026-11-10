// Live looping's layers (js/under-mix.js): earlier takes beneath this one, each from beat 0, cut to the
// loop and repeated sample for sample to the end with a short fade at each seam, or played once; several
// mixed, under full scale; any sample rate.
import { LAYER_VOLUMES, layersSeconds, SEAM_SECONDS, underLayers, underMix } from '/js/under-mix.js';
import { finaleTracks } from '/js/keep-song.js';
import { assert, close, test } from './harness.js';

const SR = 48000;
const BEAT = 0.5; // 120 bpm
const ramp = (seconds, sampleRate = SR) => Float32Array.from({ length: Math.round(seconds * sampleRate) }, (_, i) => ((i % 1000) / 1000) * 0.5 + 0.1);
const take = (seconds, sampleRate = SR) => ({ samples: ramp(seconds, sampleRate), sampleRate });

test('under mix: a loop repeats the first loop beats of the take, sample for sample, to the end', () => {
    const layer = take(5.3); // a two-bar loop (8 beats, 4 s) sung a little long
    const mix = underMix([{ ...layer, loopBeats: 8 }], { beatSeconds: BEAT, sampleRate: SR, seconds: 13 });
    const loop = 4 * SR;
    const seam = Math.round(SEAM_SECONDS * SR);
    assert(mix.length === 13 * SR, `to the end: ${mix.length}`);
    for (const i of [seam, 1000, 77777, loop - seam - 1]) {
        close(mix[i], layer.samples[i], 1e-6, `first pass, frame ${i}`);
        close(mix[i + loop], mix[i], 1e-7, `second pass, frame ${i}`);
        close(mix[i + 2 * loop], mix[i], 1e-7, `third pass, frame ${i}`);
    }
    assert(mix[loop + 10] < layer.samples[loop + 10] || Math.abs(mix[loop + 10] - layer.samples[10] * (10 / seam)) < 1e-6, 'past the loop, the take\'s start again, never its tail');
    close(mix[loop + 10], layer.samples[10] * (10 / seam), 1e-6, 'the seam fades in');
    close(mix[loop - 1], 0, 1e-9, 'and out, so it never clicks');
});

test('under mix: a take shorter than the loop is the loop with silence after it', () => {
    const mix = underMix([{ ...take(1), loopBeats: 4 }], { beatSeconds: BEAT, sampleRate: SR, seconds: 6 });
    assert(mix.length === 6 * SR, String(mix.length));
    assert(mix.slice(SR + 10, 2 * SR).every((v) => v === 0) && mix[2 * SR + 1000] !== 0, 'one second sung, one silent, then again');
});

test('under mix: several layers mix, each looped from beat 0, under full scale', () => {
    const a = { samples: new Float32Array(4 * SR).fill(0.4), sampleRate: SR, loopBeats: 8 };
    const b = { samples: new Float32Array(4 * SR).fill(0.3), sampleRate: SR, loopBeats: 8 };
    const two = underMix([a, b], { beatSeconds: BEAT, sampleRate: SR, seconds: 8 });
    close(two[SR], 0.7, 1e-6, 'two layers add');
    close(two[5 * SR], 0.7, 1e-6, 'and loop together');
    const loud = underMix([a, a, a], { beatSeconds: BEAT, sampleRate: SR, seconds: 4 });
    close(Math.max(...loud.slice(1000, 2000)), 1, 1e-6, 'three loud layers brought to full scale, never past it');
});

test('under mix: no loop plays each layer once at its own length; another sample rate is read at this one', () => {
    const once = underMix([take(2), take(3)], { beatSeconds: BEAT, sampleRate: SR });
    assert(once.length === 3 * SR, `the longest layer's length: ${once.length}`);
    const at44 = take(2, 44100);
    const read = underMix([{ ...at44, loopBeats: 4 }], { beatSeconds: BEAT, sampleRate: SR, seconds: 4 });
    assert(read.length === 4 * SR, String(read.length));
    const t = 0.7; // the same moment in both
    close(read[Math.round(t * SR)], at44.samples[Math.round(t * 44100)], 0.01, 'the 44.1 kHz take at 48 kHz, in time');
    close(read[Math.round((t + 2) * SR)], read[Math.round(t * SR)], 1e-7, 'looped at 48 kHz frames');
    assert(underMix([], { beatSeconds: BEAT, sampleRate: SR }).length === 0 && underMix([null, { samples: new Float32Array(0), sampleRate: SR }], { beatSeconds: BEAT, sampleRate: SR }).length === 0, 'nothing to mix: nothing');
});

test('under mix: a loop per layer: a one-bar bass repeats under an eight-bar entry, and the shorter divides the longer', () => {
    const bass = { samples: new Float32Array(3 * SR).fill(0.2), sampleRate: SR, loopBeats: 4 }; // sung a little long
    const entry = { samples: Float32Array.from({ length: 16 * SR }, (_, i) => (i < 16 * SR - 10 ? 0.3 : 0)), sampleRate: SR, loopBeats: 32 };
    const mix = underMix([entry, bass], { beatSeconds: BEAT, sampleRate: SR, seconds: 4 });
    assert(mix.length === 16 * SR, `the longest loop's length, past a shorter seconds: ${mix.length}`);
    const bar = 2 * SR;
    for (let k = 1; k < 8; k++) close(mix[k * bar + 5000] - mix[5000], 0, 1e-6, `the bass bar ${k + 1} the same as bar 1`);
    close(mix[5000], 0.5, 1e-6, 'both layers sounding');
});

test('under mix: layers read from exercise.under, quieter in the order listed; { step } and a shared loop still work', () => {
    const takes = { 1: take(2), 3: take(4), 5: take(1) };
    const layers = underLayers({ steps: [{ step: 3, loop: 32 }, { step: 1, loop: 4 }, 5, 9] }, takes);
    assert(layers.length === 3, 'a step with no take is left out');
    assert(layers[0].samples === takes[3].samples && layers[0].loopBeats === 32 && layers[1].loopBeats === 4 && layers[2].loopBeats === null, 'each layer its own loop, in order');
    assert(layers.map((l) => l.volume).join() === LAYER_VOLUMES.join(), `falling off: ${layers.map((l) => l.volume)}`);
    assert(underLayers({ steps: [1, 3, 5, 1] }, takes)[3].volume === LAYER_VOLUMES.at(-1), 'a fourth layer at the last volume');
    const shared = underLayers({ steps: [1, 3], loop: 8 }, takes);
    assert(shared.every((l) => l.loopBeats === 8), 'one loop for all');
    const one = underLayers({ step: 1 }, takes);
    assert(one.length === 1 && one[0].loopBeats === null && one[0].volume === 0.7, 'one { step } as before: once, at 0.7');
    assert(underLayers(null, takes).length === 0 && underLayers({ step: 2 }, takes).length === 0, 'none');
    assert(layersSeconds(layers, BEAT) === 16 && layersSeconds(one, BEAT) === 2, 'the longest stretch: a loop or a take sung through');
});

test('under mix: Keep this as a song renders a looped take out to the longest layer', () => {
    const line = [{ beat: 0, length: 4, step: 0 }];
    const lesson = {
        steps: [
            { kind: 'play', exercise: { tempo: 120, turn: false, notes: line } },
            { kind: 'play', exercise: { tempo: 120, turn: false, notes: [{ beat: 0, length: 32, step: 4 }], under: { steps: [{ step: 0, loop: 4 }] } } },
        ],
        keepSong: { title: { en: 'Loops' }, takes: [{ step: 0, name: { en: 'Bass' } }, { step: 1, name: { en: 'Top' } }], from: 1 },
    };
    const kept = { takes: { 0: { samples: new Float32Array(2.2 * SR).fill(0.25), sampleRate: SR }, 1: { samples: new Float32Array(16 * SR).fill(0.1), sampleRate: SR } } };
    const [bass, top] = finaleTracks(lesson, kept, 57);
    assert(top.samples === kept.takes[1].samples, 'a take sung through, as it was');
    assert(bass.samples.length === 16 * SR, `the bass loop out to the top's 16 s: ${bass.samples.length / SR}`);
    close(bass.samples[15 * SR], 0.25, 1e-6, 'still sounding near the end, at full volume (Sing has its own faders)');
});
