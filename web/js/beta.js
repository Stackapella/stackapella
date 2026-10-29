// A beta singer's part to learn and record (docs/beta-singers-plan.md, "Record a part"). Each
// assignment gets a project of its own, made from the bundled template at the version Hillel asked
// for, marked `beta` so it stays apart from the singer's own projects and the free limit. The
// studio opens it in its beta mode: the part in focus, the song's own key, and Check and send.

import { reactive } from 'vue';
import { api } from './api.js';
import { navigate } from './router.js';
import * as db from './db.js';
import { DEFAULT_BAR } from './quality.js';
import { local } from './storage.js';
import { loadProjects, projects, saveProject, startFromTemplate } from './stores/projects.js';
import { loadTemplate, singerOptions } from './templates.js';

export class TemplateChanged extends Error {}

/** Opens (making it the first time) the beta project for `part` (beta.parts.list's item). */
export async function startBetaRecording(part, { loop = false } = {}) {
    await loadProjects();
    const existing = projects.all.find((p) => p.beta?.assignmentId === part.id && p.template?.version === part.template.version);
    const note = { note: part.latestSubmission?.status === 'retake' ? part.latestSubmission.adminNote : null, noteBeat: part.latestSubmission?.status === 'retake' ? part.latestSubmission.adminNoteBeat : null };
    // The song's real voices so far, for learning against: on a Plus song the catalogue has none.
    note.guides = (part.guides ?? []).map(({ mine, ...recording }) => recording);
    // Practise phrase N (My parts): the studio opens with Hillel's noted phrase looped.
    note.loopOnOpen = loop;
    if (existing) {
        // Hillel's latest note (and the voices accepted since) travel with the part.
        const project = await db.get('projects', existing.id);
        await saveProject({ ...project, beta: { ...project.beta, ...note } });
        navigate(`/p/${existing.id}`);
        return existing;
    }
    const template = await loadTemplate(part.template.id);
    if (template.version !== part.template.version) {
        // The app's copy of the notes is another version than the one Hillel asked for.
        throw new TemplateChanged(`${template.id}: the app has version ${template.version}, the part is for ${part.template.version}`);
    }
    const project = await startFromTemplate(template, {
        singers: Math.max(...singerOptions(template)),
        focusKey: part.partKey,
        beta: { assignmentId: part.id, partKey: part.partKey, templateVersion: template.version, ...note },
    });
    navigate(`/p/${project.id}`);
    return project;
}

// ---- The bar for a guide (Hillel's "beta singers' scores") ----------------------------------------

const BAR_KEY = 'stackapella.beta.bar';

/** The bar a take must meet to be sent: the server's (beta.me's `bar`), kept for offline, else the default. */
export const betaBar = reactive({ ...DEFAULT_BAR, ...readBar() });

function readBar() {
    try {
        return JSON.parse(local.get(BAR_KEY) ?? 'null') ?? {};
    } catch (error) {
        console.warn('The kept bar was unreadable', error);
        return {};
    }
}

/** Keeps the bar from a beta.me or beta.invite.show response. */
export function keepBar(response) {
    const bar = response?.bar;
    if (!bar || typeof bar.pitchPercent !== 'number') return;
    Object.assign(betaBar, { pitchPercent: bar.pitchPercent, timingPercent: bar.timingPercent ?? DEFAULT_BAR.timingPercent });
    local.set(BAR_KEY, JSON.stringify({ ...betaBar }));
}

/** Fetches the singer's bar (the studio's beta mode, before recording); the kept one stays on failure. */
export async function loadBar() {
    try {
        keepBar((await api.beta.me.show()).data);
    } catch (error) {
        console.warn('The bar did not load; using the one kept', error);
    }
}

/** How many takes of an assignment have been checked on this device, and how many were under the bar. */
export function betaTries(assignmentId) {
    try {
        return { checked: 0, under: 0, ...JSON.parse(local.get(`stackapella.beta.tries.${assignmentId}`) ?? '{}') };
    } catch (error) {
        console.warn('The kept tries were unreadable', error);
        return { checked: 0, under: 0 };
    }
}

/** Counts one checked take (`ready` or not); a ready one starts the count under the bar again. */
export function countTry(assignmentId, ready) {
    const tries = betaTries(assignmentId);
    const next = { checked: tries.checked + 1, under: ready ? 0 : tries.under + 1 };
    local.set(`stackapella.beta.tries.${assignmentId}`, JSON.stringify(next));
    return next;
}
