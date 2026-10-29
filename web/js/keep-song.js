// Keep the finale (docs/courses-ideas.md, idea 6): a course finale's takes kept as a song in Sing,
// on the device. A lesson opts in with `keepSong` ({ title, takes: [{ step, name }], from, partners,
// chords }): each named step's take (this visit's, as the lesson view keeps them) becomes a recorded
// part, and the `from` step's partner lines and chords are rendered into parts beside them. Every
// take's frame 0 is its exercise's beat 0, so all of them line up at offset 0. The project is an
// ordinary song: listed, synced, shared and exported like any other (createProject counts it once as
// a song made, as Home's New song does).

import { layout } from './components/exercise.js';
import { peaks } from './dsp.js';
import { localized } from './i18n.js';
import { renderBacking, renderPart } from './synth.js';
import { keyName } from './theory.js';
import { PART_COLORS } from './theme.js';
import { createProject, savePart } from './stores/projects.js';
import { FRAMES_PER_PEAK } from './stores/session.js'; // as Sing's own takes, so trim, mix and sync treat these alike

const CHORDS_VOLUME = 0.8; // as the exercise plays them

/** Whether a lesson's finale can be kept: it opts in, and every take it names was sung this visit. */
export function canKeep(lesson, kept) {
    const spec = lesson?.keepSong;
    return !!spec && spec.takes.every((t) => kept?.takes?.[t.step]?.samples?.length);
}

/**
 * The song's tracks, pure: each kept take as it was sung, then the `from` step's partner lines and
 * chords rendered at the takes' sample rate, all from beat 0. Returns [{ name, samples, sampleRate,
 * volume }].
 */
export function finaleTracks(lesson, kept, base) {
    const spec = lesson.keepSong;
    const takes = spec.takes.map((t) => ({ name: localized(t.name), samples: kept.takes[t.step].samples, sampleRate: kept.takes[t.step].sampleRate, volume: 1, sung: true }));
    const sampleRate = takes[0].sampleRate;
    const exercise = lesson.steps[spec.from].exercise;
    const bpm = exercise.tempo ?? 72;
    const laid = layout(exercise, base);
    const partners = (laid.partners ?? []).map((line, i) => ({
        name: localized(spec.partners?.[i] ?? spec.title),
        samples: renderPart(line.notes, { bpm, sampleRate }),
        sampleRate,
        volume: line.volume,
    }));
    const chords = spec.chords && exercise.chords?.length
        ? [{ name: localized(spec.chords), samples: renderBacking(exercise.chords, { bpm, sampleRate, endBeat: laid.end, transpose: ((base % 12) + 12) % 12 }), sampleRate, volume: CHORDS_VOLUME }]
        : [];
    return [...takes, ...partners, ...chords];
}

/** Keeps the finale as a song; returns the new project. Throws LimitReached on a full free plan. */
export async function keepAsSong(lesson, kept, base) {
    const exercise = lesson.steps[lesson.keepSong.from].exercise;
    const project = await createProject({
        title: localized(lesson.keepSong.title),
        bpm: exercise.tempo ?? 72,
        key: keyName({ tonic: base, mode: 'major' }),
        timeSignature: '4/4',
        countInBars: 1,
        metronome: false,
        fromLesson: lesson.id,
    });
    const now = Date.now();
    let order = 0;
    for (const track of finaleTracks(lesson, kept, base)) {
        const part = {
            id: crypto.randomUUID(),
            projectId: project.id,
            name: track.name,
            color: PART_COLORS[order % PART_COLORS.length],
            order,
            volume: track.volume,
            pan: 0,
            muted: false,
            solo: false,
            createdAt: now,
            offsetSeconds: 0,
            nudgeSeconds: 0,
            trimStartFrames: 0,
            trimEndFrames: 0,
            recordedAt: now,
            score: null,
            sampleRate: track.sampleRate,
            frames: track.samples.length,
            framesPerPeak: FRAMES_PER_PEAK,
            peaks: peaks(track.samples, FRAMES_PER_PEAK),
            ...(track.sung ? {} : { fromLesson: true }), // the lesson's own voices, not yours (the studio's badge)
        };
        await savePart(part, track.samples);
        order++;
    }
    return project;
}
