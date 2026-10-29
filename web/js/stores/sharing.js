// Sharing a harmony (contract/api.md "Sharing: harmonies, videos and reports"): the project goes to
// the account first (the server needs it), then the mix, rendered on the device, goes up as a WAV;
// the server makes the link's audio, waveform and card. Share as a video asks the server to render
// a vertical MP4 of a section. Progress is read every couple of seconds (a timer for reading, never
// for audio).

import { api, serverReachable, uploadTo } from '../api.js';
import * as db from '../db.js';
import { encodeWav } from '../wav.js';
import { session, shareMix } from './session.js';
import { cloud, syncNow } from './cloud.js';
import { count } from '../usage.js';
import { first } from '../funnel.js';

const POLL_MS = 2000;
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const hex = (buffer) => [...new Uint8Array(buffer)].map((b) => b.toString(16).padStart(2, '0')).join('');

/** The project as the account has it (its outbox sent first); false when it isn't there (offline, or past the plan). */
async function inAccount(projectId) {
    await syncNow();
    const project = await db.get('projects', projectId);
    return !!project && project.version > 0 && !project.notSaved;
}

/**
 * Shares the open project's mix: `{ title, visibility, partId, room }`. `onStep(step)` hears
 * 'saving', 'mixing', 'sending', 'processing'. Resolves with the harmony once it's ready.
 */
// The last share's change id, by what was asked: a retry of the same choices answers the same
// harmony on the server, never a second one.
let last = null;

export async function shareProject({ title, visibility = 'unlisted', partId = null, room = 'small' }, onStep = () => {}) {
    const projectId = session.project.id;
    onStep('saving');
    // Sharing sends the mix: offline it says so, not that the song isn't saved (it may well be).
    if (!navigator.onLine) throw Object.assign(new Error('Offline'), { code: 'offline' });
    if (!(await inAccount(projectId))) {
        // The account out of reach: that's the reason, not the song (it may be saved already).
        const reachable = !cloud.offline && (await serverReachable());
        if (!reachable) throw Object.assign(new Error('Offline'), { code: 'offline' });
        throw Object.assign(new Error('The project is not in the account'), { code: 'project_missing' });
    }
    onStep('mixing');
    const mix = await shareMix({ room, partId });
    const wav = encodeWav(mix.channels, mix.sampleRate);
    onStep('sending');
    const sha256 = hex(await crypto.subtle.digest('SHA-256', await wav.arrayBuffer()));
    const asked = JSON.stringify({ projectId, partId, title: title.trim(), visibility, sha256 });
    if (last?.asked !== asked) last = { asked, changeId: crypto.randomUUID() };
    const body = {
        changeId: last.changeId,
        projectId,
        partId,
        title: title.trim().slice(0, 120),
        visibility,
        sampleRate: mix.sampleRate,
        channels: mix.channels.length,
        frames: mix.channels[0].length,
        bytes: wav.size,
        sha256,
        beatZeroSeconds: mix.beatZeroSeconds,
        guides: mix.guides,
        tracks: mix.tracks,
    };
    // A take just sent is still being processed on the server for a moment: wait for it (a minute at most).
    const create = async () => {
        for (let i = 0; ; i++) {
            try {
                return await api.harmonies.create(body);
            } catch (error) {
                if (error?.code !== 'no_takes' || i >= 20) throw error;
                await wait(3000);
                await syncNow();
            }
        }
    };
    const { data: created, upload } = await create();
    if (upload) await uploadTo(upload, wav);
    let harmony;
    try {
        harmony = (await api.harmonies.complete(created.id)).data;
    } catch (error) {
        // The file didn't arrive whole: the same change again gives a fresh upload, sent once more.
        if (error?.code !== 'upload_incomplete' && error?.code !== 'upload_mismatch') throw error;
        const again = await create();
        if (again.upload) await uploadTo(again.upload, wav);
        harmony = (await api.harmonies.complete(created.id)).data;
    }
    count('share_created');
    first('f_share'); // the funnel: this device's first share
    onStep('processing');
    for (let i = 0; i < 90 && harmony.status !== 'ready' && harmony.status !== 'failed'; i++) {
        await wait(POLL_MS);
        harmony = (await api.harmonies.show(created.id)).data;
    }
    if (harmony.status === 'failed') throw Object.assign(new Error('The mix could not be processed'), { code: 'harmony_failed', failure: harmony.failure });
    last = null; // done: the next share is a new one
    return harmony; // still 'processing' after three minutes: the sheet says it's on its way
}

/** This project's harmonies (already shared), newest first. */
export async function sharedFrom(projectId) {
    return (await api.harmonies.list({ projectId })).data;
}

/**
 * Share as a video: `{ startSeconds, endSeconds, look, words }`. A private harmony is made unlisted
 * first when `makeUnlisted` (the person agreed). Resolves with the ready video (its `url`).
 */
export async function makeVideo(harmony, choices, { makeUnlisted = false, onProgress = () => {} } = {}) {
    if (harmony.visibility === 'private' && makeUnlisted) await api.harmonies.update(harmony.id, { visibility: 'unlisted' });
    let video = (await api.videos.create(harmony.id, choices)).data;
    for (let i = 0; i < 1800 && ['queued', 'rendering'].includes(video.status); i++) {
        onProgress(video);
        await wait(POLL_MS);
        video = (await api.videos.show(video.id)).data;
    }
    if (video.status !== 'ready') throw Object.assign(new Error('The video was not made'), { code: video.status === 'failed' ? video.failure ?? 'render_error' : 'timed_out' });
    return video;
}
