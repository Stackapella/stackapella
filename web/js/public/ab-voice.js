// The Real voices page's A/B (docs/demo-pages-plan.md, feature pages): one part of a song sung by the
// app's synth voice and by a real singer, on one transport, switched with a short crossfade while
// it plays, never restarted. The real voice is the one Sing plays: the public catalogue's guide for
// the song's current version (app-voices.js), which the singer licensed for the site and our
// promotion when they recorded it (the beta agreement and Lend your voice, section 3.2). The song's
// other parts can sing underneath, as the site's four-voice player sings them.
//
//     unlock();                                              // in the tap (demo-player.js)
//     const ab = await abVoice({ song, part: 'alto' });      // renders; song is Landing::line()'s shape
//     play(button, () => ab.start());                        // { voices, length, start }
//     ab.use('real');                                        // crossfades about 30 ms, no restart
//
// Everything is on the AudioContext clock (voices.js's context, the one demo-player stops).
// No import map (public_imports_check.py).

import { renderGuide } from '../guides.js';
import { catalogueGuides, placed, realVoice } from './app-voices.js';
import { audio } from './voices.js';

const LEVEL = 0.6; // the site player's level for voices together, well under clipping
const UNDER = 0.55; // the other parts, under the one compared
const CROSSFADE = 0.03; // seconds
const FADE_OUT = 0.04;
const START_AHEAD = 0.1; // room to start every voice on the same frame

/** The parts of `song` (Landing::line()'s shape) that have a real voice for its version: their keys. */
export async function realParts(song) {
    const guides = await catalogueGuides(song.song, { version: song.version });
    return Object.keys(guides ?? {}).filter((key) => song.parts.some((p) => p.key === key));
}

/**
 * Gets `part` of `song` ready in both voices: the synth (rendered as the site's players render it)
 * and the real one, if the catalogue has it. `with: 'all'` (the default) sings the other parts under
 * it, 'none' leaves it alone. `recording` is a Float32Array at the context's rate to use as the real
 * voice instead of the catalogue's (a page that brings its own, and the tests). Resolves to
 * `{ hasReal, kind, start(), use(kind), levels() }`: start() plays it in demo-player's shape
 * (`{ voices, length, start }`), use('synth' | 'real') crossfades (or chooses before it starts), and
 * levels() reads the two gains now. Without a real voice, use('real') does nothing.
 */
export async function abVoice({ song, part, with: under = 'all', recording = null }) {
    const context = audio();
    const rate = context.sampleRate;
    const others = under === 'none' ? [] : song.parts.filter((p) => p.key !== part);
    const featured = song.parts.find((p) => p.key === part);
    if (!featured) throw new Error(`No part ${part} in ${song.song}`);
    const render = (p) => renderGuide({ templateId: `site:${song.song}`, templateVersion: song.version, part: { key: p.key, guide: { notes: p.notes } }, tempo: song.tempo, sampleRate: rate, human: 'full' });
    const [synth, ...beneath] = await Promise.all([render(featured), ...others.map(render)]);
    const guides = recording ? {} : ((await catalogueGuides(song.song, { version: song.version })) ?? {});
    const length = Math.max(synth.length, ...beneath.map((b) => b.length));
    const real = async (key) => {
        if (!guides[key]) return null;
        try {
            const voice = await realVoice(guides[key], context);
            return placed(voice.samples, voice.offsetSeconds, rate, length);
        } catch (error) {
            console.warn('A real voice could not load: the synth sings it', error);
            return null;
        }
    };
    const realSamples = recording ?? (await real(part));
    // The parts underneath sing with their real voices where they have them, as the four-voice player does.
    const underSamples = await Promise.all(others.map(async (p, i) => (await real(p.key)) ?? beneath[i]));

    let kind = 'synth';
    let gains = null; // { synth, real } while it plays
    const target = (k) => (k === kind ? 1 : 0);

    function start() {
        const at = context.currentTime + START_AHEAD;
        const master = context.createGain();
        master.gain.value = LEVEL;
        master.connect(context.destination);
        const voice = (samples, level) => {
            const buffer = context.createBuffer(1, Math.max(1, samples.length), rate);
            buffer.copyToChannel(samples, 0);
            const source = context.createBufferSource();
            source.buffer = buffer;
            const gain = context.createGain();
            gain.gain.value = level;
            source.connect(gain).connect(master);
            source.start(at);
            const ended = new Promise((resolve) => source.addEventListener('ended', resolve, { once: true }));
            return {
                gain,
                ended,
                // Fade out `after` seconds from now, on the audio clock.
                stop(after = 0) {
                    const t = context.currentTime + after;
                    gain.gain.cancelScheduledValues(t);
                    gain.gain.setTargetAtTime(0, t, FADE_OUT / 4);
                    try {
                        source.stop(t + FADE_OUT);
                    } catch (error) {
                        console.warn('the voice had stopped', error);
                    }
                },
            };
        };
        const a = voice(synth, realSamples ? target('synth') : 1);
        const b = realSamples ? voice(realSamples, target('real')) : null;
        gains = { synth: a.gain, real: b?.gain ?? null };
        const voices = [a, ...(b ? [b] : []), ...underSamples.map((s) => voice(s, UNDER))];
        Promise.all(voices.map((v) => v.ended)).then(() => {
            if (gains?.synth === a.gain) gains = null;
        });
        return { voices, length: length / rate, start: at };
    }

    function use(next) {
        if (next !== 'synth' && next !== 'real') return;
        if (next === 'real' && !realSamples) return;
        kind = next;
        if (!gains?.real) return;
        const now = context.currentTime;
        for (const k of ['synth', 'real']) {
            const param = gains[k].gain;
            param.cancelScheduledValues(now);
            param.setValueAtTime(param.value, now);
            param.linearRampToValueAtTime(target(k), now + CROSSFADE);
        }
    }

    return {
        hasReal: !!realSamples,
        get kind() {
            return kind;
        },
        start,
        use,
        levels: () => ({ synth: gains?.synth.gain.value ?? null, real: gains?.real?.gain.value ?? null }),
    };
}
