// Your voice on an idea (docs/ideas-app-plan.md, slice 3; contract/api.md, ideas.voices.*): a song
// started from an idea's tune (Sing this part) offers, under a kept take, "Add your voice to Ana's
// tune". The singer confirms their part joins it, credited; the take goes up as a take does (the
// 16-bit WAV by a presigned PUT, then complete), lined up from the tune's beat zero, and a new one
// replaces theirs. Sent at once with Try again on a failure (a lost one is sung again, not queued).

import { ApiError, api, uploadTo } from '../api.js';
import * as db from '../db.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { encodeWav } from '../wav.js';
import { account } from '../stores/account.js';
import { Icon } from './icon.js';
import { Notice } from './form.js';

const REFUSED = {
    adult_required: tk('ideas.add.refused.adult'),
    email_unverified: tk('ideas.add.refused.email'),
    ideas_closed: tk('ideas.add.refused.closed'),
    not_found: tk('ideas.add.refused.gone'),
    rate_limited: tk('ideas.add.refused.limit'),
    offline: tk('common.offline'),
};

async function sha256(blob) {
    const digest = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** The take as the idea lines voices up: from beat zero, its nudge in the offset (cut if earlier). */
export function ideaTake(samples, { offsetSeconds = 0, nudgeSeconds = 0, sampleRate }) {
    const shift = Math.round((offsetSeconds + (nudgeSeconds || 0)) * sampleRate);
    return shift >= 0 ? { samples, offsetFrames: shift } : { samples: samples.subarray(Math.min(samples.length, -shift)), offsetFrames: 0 };
}

export const IdeaVoice = {
    components: { Icon, Notice },
    props: { idea: { type: Object, required: true }, part: { type: Object, required: true }, partName: { type: String, required: true } },
    data: () => ({ rights: false, state: 'idle', error: null }),
    setup: () => ({ account }),
    template: `
        <section class="w-full space-y-3 rounded-3xl border border-edge bg-panel p-5 text-start" aria-labelledby="idea-voice-title" data-idea-voice-add :data-state="state">
            <h2 id="idea-voice-title" class="font-bold text-cream">{{ t('ideas.add.title', { name: idea.writer }) }}</h2>
            <template v-if="state === 'sent'">
                <p class="text-sm text-cream" role="status">{{ t('ideas.add.sent', { part: partName }) }}</p>
                <a :href="'/ideas/' + idea.id" class="btn-secondary btn-sm" @click.prevent="navigate('/ideas/' + idea.id)">{{ t('ideas.add.see') }}</a>
            </template>
            <template v-else-if="!account.me">
                <p class="text-sm text-muted">{{ t('ideas.add.signIn') }}</p>
                <a :href="signIn" class="btn-primary btn-sm" @click.prevent="navigate(signIn)">{{ t('auth.signIn') }}</a>
            </template>
            <form v-else class="space-y-3" @submit.prevent="send">
                <p class="text-sm text-muted">{{ t('ideas.add.what', { part: partName }) }}</p>
                <label class="check-row text-sm"><input v-model="rights" type="checkbox" class="checkbox" data-idea-voice-rights /> <span>{{ t('ideas.add.rights') }}</span></label>
                <Notice v-if="error" tone="bad">{{ error }}</Notice>
                <button type="submit" class="btn-primary btn-sm" :disabled="!rights || state === 'sending'">{{ state === 'sending' ? t('ideas.add.sending') : error ? t('ideas.add.again') : t('ideas.add.go') }}</button>
            </form>
        </section>`,
    computed: {
        signIn() {
            return `/signin?next=${encodeURIComponent(location.pathname)}`;
        },
    },
    methods: {
        navigate,
        async send() {
            this.state = 'sending';
            this.error = null;
            try {
                const recorded = await db.getAudio(this.part.id);
                if (!recorded) throw Object.assign(new Error('The take is gone from this device'), { code: 'lost' });
                const { samples, offsetFrames } = ideaTake(recorded, { offsetSeconds: this.part.offsetSeconds ?? 0, nudgeSeconds: this.part.nudgeSeconds ?? 0, sampleRate: this.part.sampleRate });
                const wav = encodeWav([samples], this.part.sampleRate);
                const created = await api.ideas.voices.create({
                    params: { id: this.idea.id },
                    body: { id: crypto.randomUUID(), partKey: this.part.templatePartKey, sampleRate: this.part.sampleRate, frames: samples.length, offsetFrames, bytes: wav.size, sha256: await sha256(wav), rightsConfirmed: true },
                });
                if (created.upload) await uploadTo(created.upload, wav);
                try {
                    await api.ideas.voices.complete({ params: { id: created.data.id } });
                } catch (error) {
                    // The server didn't get the whole file: send it once more, then confirm again.
                    if (!(error instanceof ApiError && ['upload_incomplete', 'upload_mismatch'].includes(error.code)) || !created.upload) throw error;
                    await uploadTo(created.upload, wav);
                    await api.ideas.voices.complete({ params: { id: created.data.id } });
                }
                this.state = 'sent';
            } catch (error) {
                console.warn('The voice was not added', error);
                const code = error?.code;
                this.error = code === 'lost' ? t('ideas.add.refused.lost') : t(REFUSED[code] ?? tk('ideas.add.refused.other'));
                this.state = 'idle';
            }
        },
    },
};
