// "Make a template from this project" (docs/template-creator-plan.md, "Where it lives"): each
// recorded part of the project is transcribed into a part, highest first (the highest is the tune;
// up to four, as soprano, alto, tenor and bass), on the project's tempo and time signature. A
// template project starts from its own template instead, its recorded parts' notes in place of the
// guides they sang. The creator's editor opens on the result. Harmonise this takes one take of an
// own song as the tune, for the creator's Parts step to suggest the rest under it.

import { takesOf } from '../../stores/projects.js';
import { trackTake } from '../../pitchtrack.js';
import { transcribe, findKey } from '../../transcribe.js';
import { copyOf, gridFor, newTemplate, partOf, phrasesOf, sectionFrom } from '../../creator.js';
import { t } from '../../i18n.js';

const KEYS = ['soprano', 'alto', 'tenor', 'bass'];
const NAMES = { soprano: 'Soprano', alto: 'Alto', tenor: 'Tenor', bass: 'Bass' }; // i18n-ignore: the file's English names

// Harmonise my song: a take is too unclear to arrange from with fewer notes than this, or more than
// this share of them unsure (or when the transcriber found it empty or noisy).
const FEWEST_NOTES = 4;
const MOST_UNSURE = 0.4;

/** The notes sung in a part's take, in the project's beats, and whether they're clear enough to arrange from. */
function sungIn(part, samples, { tempo, timeSignature }) {
    const track = trackTake(samples, part.sampleRate);
    const offset = (part.offsetSeconds ?? 0) + (part.nudgeSeconds ?? 0);
    const result = transcribe(track, { beatSeconds: 60 / tempo, startSeconds: -offset, startBeat: 0, grid: gridFor(timeSignature) });
    const kept = result.notes.filter((n) => n.beat >= 0);
    const unsure = kept.filter((n) => n.sure === false).length;
    const clear = !result.empty && !result.noisy && kept.length >= FEWEST_NOTES && unsure <= kept.length * MOST_UNSURE;
    return { notes: kept.map(({ beat, length, pitch }) => ({ beat, length, pitch })), clear };
}

const notesOf = (part, samples, song) => sungIn(part, samples, song).notes;

/**
 * Harmonise this (docs/harmonise-my-song-plan.md): the tune from one take of an own song, as a
 * template to suggest the other parts under, and whether the take was clear enough to.
 */
export async function harmoniseFrom(projectId, partId) {
    const { project, parts, takes } = await takesOf(projectId);
    const part = parts.find((p) => p.id === partId);
    if (!project || !part || !takes.has(part.id)) throw Object.assign(new Error('nothing recorded'), { code: 'nothing_recorded' });
    const tempo = project.bpm || 90;
    const timeSignature = project.timeSignature || '4/4';
    const { notes, clear } = sungIn(part, takes.get(part.id), { tempo, timeSignature });
    if (!clear) return { template: null, clear };
    return { template: made([notes], { tempo, timeSignature, title: project.title || '' }), clear };
}

/** A template from sung parts' notes, highest first: the highest is the tune. */
function made(sung, { tempo, timeSignature, title }) {
    const median = (notes) => [...notes.map((n) => n.pitch)].sort((a, b) => a - b)[notes.length >> 1];
    const ordered = [...sung].sort((a, b) => median(b) - median(a));
    const template = { ...newTemplate({ tempo, timeSignature }), title };
    template.parts = ordered.slice(0, 4).map((notes, i) => partOf(KEYS[i], NAMES[KEYS[i]], notes, i === 0 ? { melody: true } : {}));
    template.key = findKey(template.parts[0].guide.notes).name;
    const end = Math.max(...template.parts.flatMap((p) => p.guide.notes.map((n) => n.beat + n.length)));
    template.sections = sectionFrom(phrasesOf(template), t('create.sectionName'), end);
    return template;
}

/** A template from a project (and, when given, only that part as the tune). */
export async function templateFromProject(projectId, onlyPartId = null) {
    const { project, parts, template, takes } = await takesOf(projectId);
    if (!project) throw new Error('not_found');
    const tempo = project.bpm || template?.tempo || 90;
    const timeSignature = project.timeSignature || '4/4';
    const recorded = parts.filter((p) => takes.has(p.id) && (!onlyPartId || p.id === onlyPartId));
    const sung = recorded.map((part) => ({ part, notes: notesOf(part, takes.get(part.id), { tempo, timeSignature }) })).filter((s) => s.notes.length);
    if (template) {
        // A template's project: its parts as written, with what was sung in place of each guide.
        const copy = copyOf(template, t('create.copyTitle', { title: project.title || template.title }));
        for (const { part, notes } of sung) {
            const at = copy.parts.findIndex((p) => p.key === part.templatePartKey);
            if (at >= 0) copy.parts[at] = partOf(copy.parts[at].key, copy.parts[at].name, notes, copy.parts[at].melody ? { melody: true } : {});
        }
        return copy;
    }
    if (!sung.length) throw Object.assign(new Error('nothing recorded'), { code: 'nothing_recorded' });
    return made(sung.map((s) => s.notes), { tempo, timeSignature, title: project.title || '' });
}
