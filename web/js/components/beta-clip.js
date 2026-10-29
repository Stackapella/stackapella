// A course clip's card on My parts (views/beta.js; the clip's brief from the server, CourseClips):
// the course, what to sing, where it sits against the singer's comfortable note and how long it is,
// then a light recorder right in the card (js/beta-clip.js): three beats counted in, the clip's
// seconds with a bar filling, a stop by itself, the device's verdict, Hear it back, and Send (the
// outbox keeps it until it's sent, as a song's part).

import { audio } from '../audio.js';
import { describeDevice, enqueue } from '../beta-outbox.js';
import { checkClip, COUNT_IN, readyClipMic, recordClip } from '../beta-clip.js';
import { hearNotes, hearTake } from '../creator-audio.js';
import { t, tk } from '../i18n.js';
import { learning } from '../stores/learning.js';
import { BETA_STATUS } from './beta.js';
import { Notice, StatusChip } from './form.js';
import { Icon } from './icon.js';
import { toast } from './ui.js';

const MISSES = { silent: tk('beta.check.silent'), quiet: tk('beta.check.quiet'), clipping: tk('beta.check.clipping'), short: tk('beta.clips.short') };
const FIXES = { silent: tk('beta.fix.silent'), quiet: tk('beta.fix.quiet'), clipping: tk('beta.fix.clipping'), short: tk('beta.clips.shortFix') };

export const BetaClip = {
    name: 'BetaClip',
    components: { Icon, Notice, StatusChip },
    props: {
        item: { type: Object, required: true }, // the part on My parts, kind "clip"
        canRecord: { type: Boolean, default: true },
    },
    emits: ['queued'],
    setup: () => ({ BETA_STATUS, MISSES, FIXES, COUNT_IN }),
    data: () => ({
        stage: 'idle', // idle | counting | singing | checked
        at: 0, // seconds into the clip (negative while counting in)
        take: null,
        check: null,
        recorder: null,
        busy: false,
    }),
    computed: {
        clip() {
            return this.item.clip;
        },
        /** Where it sits: the comfortable note, a step from it, or a stretch around it. */
        pitchLine() {
            const pitch = this.clip.pitch;
            if (!pitch) return null;
            if (typeof pitch.step === 'number') {
                if (pitch.step === 0) return t('beta.clips.onHome');
                const count = Math.abs(pitch.step);
                return pitch.step > 0 ? t('beta.clips.aboveHome', { count }) : t('beta.clips.belowHome', { count });
            }
            if (typeof pitch.low === 'number' && typeof pitch.high === 'number') return t('beta.clips.aroundHome', { low: Math.abs(pitch.low), high: Math.abs(pitch.high) });
            return null;
        },
        /** The note to hear first: the comfortable note found in the course, moved by the clip's step. */
        note() {
            const home = learning.progress.home;
            if (typeof home !== 'number') return null;
            return home + (typeof this.clip.pitch?.step === 'number' ? this.clip.pitch.step : 0);
        },
        recordable() {
            return this.item.recordable && this.canRecord && !['submitted', 'in_review', 'guide'].includes(this.item.status);
        },
        chip() {
            return BETA_STATUS[this.item.status] ?? BETA_STATUS.to_record;
        },
        misses() {
            return this.check ? Object.keys(MISSES).filter((key) => this.check[key]) : [];
        },
        progress() {
            return Math.max(0, Math.min(1, this.at / this.clip.seconds));
        },
        countdown() {
            return Math.max(1, Math.ceil(-this.at));
        },
    },
    unmounted() {
        this.recorder?.stop();
    },
    methods: {
        async hearNote() {
            if (this.note !== null) await hearNotes([{ beat: 0, length: 2, pitch: this.note }], { tempo: 60 });
        },
        async record() {
            if (this.busy) return;
            this.busy = true;
            try {
                if (!(await readyClipMic())) return;
                this.take = null;
                this.check = null;
                this.at = -COUNT_IN;
                this.stage = 'counting';
                this.recorder = recordClip({
                    seconds: this.clip.seconds,
                    onFrame: ({ seconds }) => {
                        this.at = seconds;
                        if (seconds >= 0 && this.stage === 'counting') this.stage = 'singing';
                    },
                });
                const take = await this.recorder.done;
                this.recorder = null;
                if (!take) {
                    this.stage = 'idle';
                    return;
                }
                this.take = take;
                this.check = checkClip(take, this.clip.seconds);
                this.stage = 'checked';
            } catch (error) {
                console.error('The clip could not record', error);
                this.stage = 'idle';
                toast(t('beta.clips.micFailed'), { tone: 'error', seconds: 10 });
            } finally {
                this.busy = false;
            }
        },
        stop() {
            this.recorder?.stop();
        },
        async hearBack() {
            if (this.take) await hearTake(this.take);
        },
        async send() {
            if (!this.take || !this.check?.ready || this.busy) return;
            this.busy = true;
            try {
                // A clip has no song: no template, version or key (the server checks that).
                await enqueue({ assignmentId: this.item.id, templateId: null, partKey: null, samples: this.take.samples, sampleRate: this.take.sampleRate, latencyFrames: audio.latencyFrames ?? 0, device: describeDevice(), mic: audio.mic?.label ?? '', scores: { verdict: 'ready' }, note: null });
                this.take = null;
                this.check = null;
                this.stage = 'idle';
                this.$emit('queued');
            } catch (error) {
                console.error('The clip could not be kept to send', error);
                toast(t('beta.clips.keepFailed'), { tone: 'error', seconds: 10 });
            } finally {
                this.busy = false;
            }
        },
    },
    template: `
        <li class="card space-y-3 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-cream" translate="no">{{ clip.courseTitle }}</h3>
                    <p class="text-xs text-muted">{{ t('beta.clips.details', { seconds: clip.seconds }) }}</p>
                </div>
                <StatusChip :tone="chip.tone" :label="t(chip.label)" />
            </div>
            <div v-if="item.status === 'retake' && item.latestSubmission?.adminNote" class="flex gap-3 rounded-2xl bg-raised p-3">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-panel text-xs font-bold text-cream" aria-hidden="true">H</span>
                <p class="min-w-0 text-sm text-cream" translate="no">{{ item.latestSubmission.adminNote }}</p>
            </div>
            <!-- The brief comes from the server in the singer's language. -->
            <p class="text-sm text-cream" translate="no">{{ clip.sing }}</p>
            <p v-if="pitchLine" class="text-sm text-muted">{{ pitchLine }}</p>
            <p v-if="!item.recordable" class="text-sm text-muted">{{ t('beta.parts.notOpen') }}</p>
            <p v-else-if="['submitted', 'in_review'].includes(item.status)" class="text-sm text-muted">{{ t('beta.parts.waiting') }}</p>

            <!-- Recording: the count-in, then a bar filling over the clip's seconds. -->
            <div v-if="stage === 'counting' || stage === 'singing'" class="space-y-2" role="status" aria-live="polite">
                <p class="text-sm font-semibold text-cream">{{ stage === 'counting' ? t('beta.clips.countIn', { count: countdown }) : t('beta.clips.singNow') }}</p>
                <div class="h-2 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full bg-accent" :style="{ width: progress * 100 + '%' }"></div></div>
            </div>

            <!-- The device's verdict: ready, or what to fix. -->
            <template v-if="stage === 'checked' && check">
                <Notice v-if="check.ready" tone="good">{{ t('beta.clips.ready') }}</Notice>
                <Notice v-else tone="warn">
                    <span v-for="miss in misses" :key="miss" class="block">{{ t(MISSES[miss]) }} {{ t(FIXES[miss], { seconds: clip.seconds }) }}</span>
                </Notice>
            </template>

            <div v-if="recordable" class="flex flex-wrap gap-2">
                <template v-if="stage === 'counting' || stage === 'singing'">
                    <button type="button" class="btn-secondary btn-sm" @click="stop"><Icon name="stop" :size="14" /> {{ t('studio.stop') }}</button>
                </template>
                <template v-else>
                    <button v-if="note !== null" type="button" class="btn-ghost btn-sm" @click="hearNote"><Icon name="play" :size="14" /> {{ t('beta.clips.hearNote') }}</button>
                    <button v-if="stage === 'checked'" type="button" class="btn-secondary btn-sm" @click="hearBack"><Icon name="play" :size="14" /> {{ t('beta.clips.hearBack') }}</button>
                    <button type="button" class="btn-sm" :class="stage === 'checked' && check?.ready ? 'btn-secondary' : 'btn-primary'" :disabled="busy" @click="record"><Icon name="mic" :size="14" /> {{ stage === 'checked' || item.latestSubmission ? t('beta.clips.recordAgain') : t('beta.clips.record') }}</button>
                    <button v-if="stage === 'checked' && check?.ready" type="button" class="btn-primary btn-sm" :disabled="busy" @click="send">{{ t('beta.clips.send') }}</button>
                </template>
            </div>
        </li>
    `,
};
