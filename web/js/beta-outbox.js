// Beta takes waiting to be sent (docs/beta-singers-plan.md, "Send"). A take is kept on the device,
// in the betaTakes store, until the server has it: ask for an upload (beta.submissions.create),
// PUT the WAV, then confirm (beta.submissions.complete). A presigned PUT is all or nothing, so a
// failure starts the take's send again from the start when the connection is back. A take that
// was already uploaded is confirmed first (confirming twice is safe), so a lost answer never
// sends it twice. The WAV is kept apart from the take's details (in the `audio` store, under
// `betaTake:<id>`), so listing the outbox never reads every take into memory.

import { reactive } from 'vue';
import { api, ApiError, uploadTo } from './api.js';
import * as db from './db.js';
import { encodeWav } from './wav.js';
import { account, loadAccount } from './stores/account.js';

/** What My parts shows: every take still on the device, with its state. */
export const outbox = reactive({ takes: [], sending: false });

/** Refusals that sending again won't fix: the take needs recording again (or Hillel). */
const FINAL = new Set(['not_yours', 'not_recordable', 'template_changed', 'validation_failed', 'singer_revoked', 'below_bar']);
// Signed out, or signed in as someone who isn't the singer: the take waits for the singer's account.

export async function loadOutbox() {
    outbox.takes = (await db.all('betaTakes').catch(() => [])).map(summary).sort((a, b) => a.createdAt - b.createdAt);
}

function summary(take) {
    const { wav, ...rest } = take; // a take kept before the WAV moved out still has it inline
    return rest;
}

const wavKey = (id) => `betaTake:${id}`;

/** A take's WAV bytes. */
async function wavOf(take) {
    return take.wav ?? (await db.get('audio', wavKey(take.id)));
}

/** Sends what's waiting whenever the connection comes back or the app comes to the front. */
if (typeof window !== 'undefined') {
    window.addEventListener('online', () => sendAll().catch((error) => console.error('Sending takes failed', error)));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && outbox.takes.some((take) => take.state === 'waiting')) {
            sendAll().catch((error) => console.error('Sending takes failed', error));
        }
    });
}

/**
 * Keeps a take to send: `{ assignmentId, templateId, partKey, templateVersion, key, samples,
 * sampleRate, latencyFrames, device, mic, scores, note }`. Returns its id. The take is stored as
 * the WAV that's uploaded (mono, 16-bit), dry, as recorded.
 */
export async function enqueue(take) {
    const wav = await encodeWav([take.samples], take.sampleRate).arrayBuffer();
    const record = {
        id: crypto.randomUUID(),
        assignmentId: take.assignmentId,
        templateId: take.templateId,
        partKey: take.partKey,
        templateVersion: take.templateVersion,
        key: take.key,
        sampleRate: take.sampleRate,
        frames: take.samples.length,
        latencyFrames: take.latencyFrames,
        bytes: wav.byteLength,
        sha256: await sha256(wav),
        device: take.device,
        mic: take.mic,
        scores: JSON.parse(JSON.stringify(take.scores ?? null)), // a plain copy: the report's is reactive
        note: take.note || null,
        state: 'waiting', // waiting | sending | refused
        refusal: null,
        submissionId: null,
        upload: null,
        createdAt: Date.now(),
    };
    await db.put('audio', wav, wavKey(record.id));
    await db.put('betaTakes', record);
    await loadOutbox();
    return record.id;
}

let again = false;

/**
 * Sends every waiting take, one after another. Safe to call at any time: a call while sending
 * makes the run go round once more, for a take kept meanwhile.
 */
export async function sendAll() {
    if (outbox.sending) {
        again = true;
        return;
    }
    // Only a singer's account sends (another account signed in on this device leaves them waiting).
    if (!account.loaded) await loadAccount();
    if (!account.me?.betaSinger) return;
    outbox.sending = true;
    try {
        do {
            again = false;
            for (const take of await db.all('betaTakes')) {
                if (take.state === 'refused') continue;
                try {
                    await send(take);
                } catch (error) {
                    console.error('A take could not be sent', error); // the next take still goes
                }
            }
        } while (again);
    } finally {
        outbox.sending = false;
        await loadOutbox();
    }
}

/** One take: create (if needed), upload, confirm. Leaves it waiting on anything temporary. */
async function send(take) {
    await save(take, { state: 'sending' });
    try {
        const wav = await wavOf(take);
        if (!wav) {
            await save(take, { state: 'refused', refusal: 'lost' }); // storage was cleared: record it again
            return;
        }
        if (take.submissionId && take.uploaded) {
            // Uploaded, but the confirmation's answer was lost: confirm again, upload only if asked.
            try {
                await api.beta.submissions.complete(take.submissionId);
                await sent(take);
                return;
            } catch (error) {
                if (!(error instanceof ApiError && ['upload_incomplete', 'upload_mismatch'].includes(error.code))) throw error;
                await save(take, { uploaded: false });
            }
        }
        const fresh = take.upload && Date.parse(take.upload.expiresAt) > Date.now() + 60000;
        if (!take.submissionId || !fresh) {
            const created = await api.beta.submissions.create({
                assignmentId: take.assignmentId,
                // A song's part names its version and key; a course clip has neither, so they're left out.
                ...(take.templateVersion != null ? { templateVersion: take.templateVersion, key: take.key } : {}),
                sampleRate: take.sampleRate,
                frames: take.frames,
                latencyFrames: take.latencyFrames,
                bytes: take.bytes,
                sha256: take.sha256,
                device: take.device,
                mic: take.mic,
                scores: take.scores,
                note: take.note,
            });
            await save(take, { submissionId: created.data.id, upload: created.upload, uploaded: false });
        }
        await uploadTo(take.upload, wav);
        await save(take, { uploaded: true });
        try {
            await api.beta.submissions.complete(take.submissionId);
        } catch (error) {
            // The server didn't get the whole file: send it once more, then confirm again.
            if (!(error instanceof ApiError && ['upload_incomplete', 'upload_mismatch'].includes(error.code))) throw error;
            await uploadTo(take.upload, wav);
            await api.beta.submissions.complete(take.submissionId);
        }
        await sent(take);
    } catch (error) {
        const code = error instanceof ApiError ? error.code : 'server_error';
        if (FINAL.has(code)) {
            await save(take, { state: 'refused', refusal: code });
        } else {
            if (error instanceof ApiError && error.code === 'upload_failed') await save(take, { upload: null, uploaded: false }); // a new upload next time
            await save(take, { state: 'waiting', refusal: code });
        }
    }
}

/** The server has it: the take leaves the device. */
async function sent(take) {
    await db.remove('betaTakes', take.id);
    await db.remove('audio', wavKey(take.id));
}

async function save(take, fields) {
    Object.assign(take, fields);
    await db.put('betaTakes', take);
}

/** Removes a take the singer won't send (one the server refused, or one they recorded again). */
export async function discard(id) {
    await db.remove('betaTakes', id);
    await db.remove('audio', wavKey(id));
    await loadOutbox();
}

/**
 * A take as from the song's start, which is how Hillel's review and the guide line it up: one
 * recorded from later (or nudged later) is padded with silence, one nudged earlier is cut.
 */
export function alignedTake(samples, { offsetSeconds = 0, nudgeSeconds = 0, sampleRate }) {
    if (!samples) return null;
    const shift = Math.round((offsetSeconds + (nudgeSeconds || 0)) * sampleRate);
    if (shift === 0) return samples;
    if (shift < 0) return samples.subarray(Math.min(samples.length, -shift));
    const out = new Float32Array(shift + samples.length);
    out.set(samples, shift);
    return out;
}

async function sha256(bytes) {
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** "iPhone, Safari 18 (installed)": the device and browser, for Hillel's review. */
export function describeDevice(ua = navigator.userAgent, installed = matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true) {
    const platform = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android' : /Mac OS X/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'Windows' : /Linux/.test(ua) ? 'Linux' : 'Unknown'; // i18n-ignore: names for Hillel
    const browser = (/Edg\/(\d+)/.exec(ua) && ['Edge', /Edg\/(\d+)/.exec(ua)[1]]) // i18n-ignore: names for Hillel
        ?? (/(?:Chrome|CriOS)\/(\d+)/.exec(ua) && ['Chrome', /(?:Chrome|CriOS)\/(\d+)/.exec(ua)[1]]) // i18n-ignore: names for Hillel
        ?? (/(?:Firefox|FxiOS)\/(\d+)/.exec(ua) && ['Firefox', /(?:Firefox|FxiOS)\/(\d+)/.exec(ua)[1]]) // i18n-ignore: names for Hillel
        ?? (/Version\/(\d+).*Safari/.exec(ua) && ['Safari', /Version\/(\d+).*Safari/.exec(ua)[1]]) // i18n-ignore: names for Hillel
        ?? ['Browser', '']; // i18n-ignore: names for Hillel
    return `${platform}, ${browser.join(' ').trim()}${installed ? ' (installed)' : ''}`; // i18n-ignore: for Hillel, not shown to singers
}
