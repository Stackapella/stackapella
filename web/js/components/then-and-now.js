// Then and now for a play step (docs/courses-ideas.md, idea 4): a course's lesson 1 keeps its first
// heard take (exercise.keep), and its finale (exercise.thenAndNow) plays that take beside today's.
// Both stay on this device in IndexedDB under `course:<id>`, as the wave, bullseye and long-note
// steps keep theirs: never synced, never sent. Only by ear, with no score, so both skins read the same.

import * as db from '../db.js';
import { engine, unlock } from '../audio.js';
import { formatDate, t } from '../i18n.js';
import { Icon } from './icon.js';
import { count } from '../usage.js';

const keyOf = (id) => `course:${id}`;

/** Keeps a step's first heard take: once, and never over an earlier one. True when it kept this one. */
export async function keepFirstTake(id, take, store = db) {
    if (!id || !take?.samples?.length) return false;
    const key = keyOf(id);
    if (await store.get('learning', key)) return false;
    await store.putAudio(key, take.samples);
    await store.put('learning', { sampleRate: take.sampleRate, at: Date.now() }, key);
    return true;
}

/** The kept take for `id` ({ samples, sampleRate, at }), or null when this device has none. */
export async function loadKept(id, store = db) {
    const kept = await store.get('learning', keyOf(id));
    const samples = kept ? await store.getAudio(keyOf(id)) : null;
    return kept && samples?.length ? { ...kept, samples } : null;
}

export const ThenAndNow = {
    components: { Icon },
    props: {
        id: { type: String, required: true },
        now: { type: Object, default: null }, // today's take: { samples, sampleRate }
    },
    data: () => ({ kept: undefined, playing: null }),
    template: `
        <section class="space-y-3 rounded-2xl border border-edge bg-panel p-4" aria-labelledby="then-and-now-title" data-then-and-now>
            <h2 id="then-and-now-title" class="font-bold text-cream">{{ t('course.then.title') }}</h2>
            <template v-if="kept">
                <div v-for="row in rows" :key="row.id" class="flex items-center gap-3" :data-then-row="row.id">
                    <button type="button" class="grid size-10 shrink-0 place-items-center rounded-full glass-bubble-solid" :aria-label="playing === row.id ? t('studio.stop') : t('course.then.hear', { which: row.name })" :disabled="!row.take" @click="hear(row)"><Icon :name="playing === row.id ? 'stop' : 'play'" :size="16" /></button>
                    <p class="min-w-0 text-sm"><span class="block font-semibold text-cream">{{ row.name }}</span><span v-if="row.line" class="text-muted">{{ row.line }}</span></p>
                </div>
            </template>
            <p v-else-if="kept === null" class="text-sm text-muted">{{ t('course.then.none') }}</p>
        </section>`,
    computed: {
        rows() {
            return [
                { id: 'then', name: t('course.then.then'), line: t('course.then.sung', { date: formatDate(this.kept.at) }), take: this.kept },
                { id: 'now', name: t('course.then.now'), line: null, take: this.now },
            ];
        },
    },
    async created() {
        try {
            this.kept = await loadKept(this.id);
        } catch (error) {
            console.error('Then and now not loaded', error);
            this.kept = null;
        }
    },
    unmounted() {
        this.source?.stop();
    },
    methods: {
        /** One take, on the audio clock; tapping the one playing stops it. */
        async hear(row) {
            const was = this.playing;
            this.source?.stop();
            this.source = null;
            this.playing = null;
            if (was === row.id || !row.take) return;
            count('then_now');
            await unlock();
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(row.take.samples, row.take.sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                if (this.source === source) {
                    this.source = null;
                    this.playing = null;
                }
            };
            source.start(engine.ctx.currentTime + 0.03);
            this.source = source;
            this.playing = row.id;
        },
    },
};
