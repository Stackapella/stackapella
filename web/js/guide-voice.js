// A library song's part as the app plays it outside Sing (Hillel, 2026-11-02: "when i play the song on the marketing
// homepage I hear the real voices but in the app when I click preview ... I hear the old sounds"): Home's Preview
// and the song page's Hear all parts and each part's play. The part's real voice where the catalogue has one for
// this version of the song (both play at the song's own key and full speed, so recordingApplies holds), the same
// voice Sing and the landing play; otherwise the synth, as before. Sing itself chooses per project
// (stores/session.js voiceChoice, a singer's chosen voices too); this is the song's default voice.

import { guideKey, loadRecordedGuide, recordingApplies } from './recorded-guides.js';
import { catalogueStore } from './lazy-stores.js';

/**
 * `part` of `template` at `sampleRate`: `{ samples, offsetSeconds, real }`, its real voice when one applies and loads,
 * else what `synth()` renders (offset 0).
 */
export async function partVoice(template, part, sampleRate, synth) {
    const { loadCatalogue, versionsFor } = await catalogueStore();
    await loadCatalogue();
    const found = versionsFor(template.id, part.key);
    const recording = found ? (found.versions.find((v) => v.default) ?? found.versions[0]) : null;
    if (recording && recordingApplies({ recording, version: found.version, template })) {
        try {
            const decoder = new OfflineAudioContext(1, 1, sampleRate);
            const voice = await loadRecordedGuide(recording, {
                key: guideKey(template.id, template.version, part.key, recording.id),
                sampleRate,
                decode: (bytes) => decoder.decodeAudioData(bytes),
            });
            return { samples: voice.samples, offsetSeconds: voice.offsetSeconds, real: true };
        } catch (error) {
            console.warn('The real voice could not load: the synth sings it', error);
        }
    }
    return { samples: await synth(), offsetSeconds: 0, real: false };
}
