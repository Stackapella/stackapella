// YouTube sync's tool (Library > YouTube sync; docs/youtube-sync-plan.md, Hillel 2026-11-19): pick a song, find a real
// recording of it on YouTube (a search, at most 100 a day for the whole app, or a pasted link), and make its sync map:
// the mic hears the video once through the speakers and suggests the key, tuning, verses and bar lines (js/listen-align.js),
// then the map is checked with our lane running under the video, fixed by tapping bar lines or nudging, and saved as a
// draft. Someone else plays it through and approves it; only then do singers see it. Only the map is kept: the heard
// audio is dropped in this page as soon as the suggestion is made.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { audio, chooseMic, currentMicId, engine, micChoices, openMic, closeMic, refreshMics } from '../audio.js';
import { EmptyState, StatusChip } from '../components/form.js';
import { Modal, toast } from '../components/ui.js';
import { LanePainter } from '../lane-painter.js';
import { nameNote } from '../note-names.js';
import { token } from '../colors.js';
import { barAt } from '../theory.js';
import { transposeKey } from '../staff-layout.js';
import { barLines, beatAt, mapProblems, unroll } from '../sync-map.js';
import { hearChroma, suggest, toVideo } from '../listen-align.js';
import { videoClock } from '../video-clock.js';
import { micDb } from '../mic-check.js';
import { LevelMeter } from '../components/level-meter.js';
import { openPlayer, videoIdFrom } from '../youtube-player.js';
import { errorsFrom, LoadFailed, refused, when } from './common.js';

const HARMONIES = ['same', 'similar', 'different'];
const HARMONY_NAMES = { same: tk('admin.songVideos.harmony.same'), similar: tk('admin.songVideos.harmony.similar'), different: tk('admin.songVideos.harmony.different') };
const HIDDEN_NAMES = {
    removed: tk('admin.songVideos.hidden.removed'),
    private: tk('admin.songVideos.hidden.private'),
    not_embeddable: tk('admin.songVideos.hidden.notEmbeddable'),
    made_for_kids: tk('admin.songVideos.hidden.madeForKids'),
    age_restricted: tk('admin.songVideos.hidden.ageRestricted'),
};
const MOST_VERSES = 5; // the forms the mic step tries: one verse up to this many
const NUDGE = 0.05; // seconds per nudge of the whole map
const SHOWN = 6; // seconds across the lane

/** The forms the suggestion tries: the song's sections in order, repeated 1 to MOST_VERSES times. */
export function candidateForms(template, most = MOST_VERSES) {
    const once = template.sections.map((_, section) => ({ kind: 'section', section, shift: 0, cents: 0 }));
    return Array.from({ length: most }, (_, k) => Array.from({ length: k + 1 }, (_, verse) => once.map((s) => ({ ...s, ...(verse ? { words: false, name: t('admin.songVideos.verseName', { n: verse + 1 }) } : {}) }))).flat());
}

/** `m:ss` for a number of seconds (not `clock`: this.clock is the video's clock, and a setup name it shadows). */
const minutes = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

export const SongVideosSection = {
    components: { EmptyState, LevelMeter, LoadFailed, Modal, StatusChip },
    props: { me: { type: Object, required: true } },
    setup: () => ({ HARMONIES, HARMONY_NAMES, HIDDEN_NAMES, minutes, when, audio, micChoices, currentMicId }),
    data: () => ({
        songs: null, failed: false, slug: '', template: null, version: null, rows: null,
        query: '', link: '', results: null, searching: false, searchError: null,
        video: null, playerError: null, ready: false, partKey: null,
        form: [], anchors: [], harmony: 'same', saving: false, saveErrors: null,
        listening: null, heardDb: null, heardSeconds: 0, working: false, said: null, tapping: null,
        removing: null, busy: false,
    }),
    computed: {
        whole() {
            return this.template && this.form.length && !mapProblems(this.template, { form: this.form, anchors: [[0, 0], [1, 0.001]] }).some((p) => p.startsWith('form'))
                ? unroll(this.template, this.form)
                : null;
        },
        problems() {
            return this.template ? mapProblems(this.template, { form: this.form, anchors: this.anchors }) : [];
        },
        barLines() {
            return this.whole ? barLines(this.template, this.form) : [];
        },
        barBeats() {
            return this.template ? barAt(this.template, this.template.pickupBeats ?? 0).beats : 3;
        },
        keyName() {
            const first = this.form.find((s) => s.kind === 'section');
            return this.template ? transposeKey(this.template.key, first?.shift ?? 0) : '';
        },
    },
    watch: {
        whole() {
            this.paintNotes();
        },
        partKey() {
            this.paintNotes();
        },
    },
    created() {
        this.loadSongs();
        refreshMics().catch((error) => console.warn('Mics not listed', error)); // named once the mic has been allowed
    },
    beforeUnmount() {
        this.stopLoop();
        this.closeVideo();
        if (this.listening) this.dropListening();
        window.removeEventListener('keydown', this.onKey);
    },
    template: `
        <div class="space-y-6" data-song-videos>
            <div class="space-y-1">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.songVideos') }}</h1>
                <p class="max-w-prose text-sm text-muted">{{ t('admin.songVideos.intro') }}</p>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="loadSongs" />
            <p v-else-if="!songs" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <label v-else class="grid max-w-md gap-1 text-sm font-semibold text-cream">{{ t('admin.songVideos.song') }}
                <select v-model="slug" class="input" data-song-videos-song @change="pickSong">
                    <option value="" disabled>{{ t('admin.songVideos.pickSong') }}</option>
                    <option v-for="s in songs" :key="s.id" :value="s.id" translate="no">{{ s.title }}</option>
                </select>
            </label>

            <section v-if="template" class="space-y-3" data-song-videos-rows>
                <h2 class="text-lg font-bold text-cream">{{ t('admin.songVideos.synced') }}</h2>
                <p v-if="!rows" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                <EmptyState v-else-if="!rows.length" icon="info" :title="t('admin.songVideos.noneYet')" />
                <ul v-else class="space-y-3">
                    <li v-for="row in rows" :key="row.id" class="card flex flex-wrap items-center justify-between gap-3 p-4" :data-song-video="row.id" :data-status="row.status">
                        <div class="min-w-0">
                            <p class="font-semibold text-cream" translate="no">{{ row.title }}</p>
                            <p class="text-sm text-muted"><span translate="no">{{ row.channel }}</span> · {{ minutes(row.durationSeconds) }} · {{ t('admin.songVideos.syncedOn', { name: row.syncedBy?.name ?? '', when: when(row.checkedAt) }) }}</p>
                        </div>
                        <span class="flex flex-wrap items-center gap-2">
                            <StatusChip v-if="row.hiddenReason" tone="bad" :label="t(HIDDEN_NAMES[row.hiddenReason] ?? HIDDEN_NAMES.removed)" />
                            <StatusChip :tone="row.status === 'approved' ? 'good' : 'warn'" :label="row.status === 'approved' ? t('admin.songVideos.approved') : t('admin.songVideos.draft')" />
                            <button type="button" class="btn-secondary btn-sm" data-song-video-open @click="openRow(row)">{{ t('admin.songVideos.open') }}</button>
                            <button v-if="row.status !== 'approved'" type="button" class="btn-secondary btn-sm" :disabled="busy" data-song-video-approve @click="approve(row)">{{ t('admin.songVideos.approve') }}</button>
                            <button type="button" class="btn-secondary btn-sm" data-song-video-remove @click="removing = row">{{ t('admin.songVideos.remove') }}</button>
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="template" class="space-y-3" data-song-videos-find>
                <h2 class="text-lg font-bold text-cream">{{ t('admin.songVideos.find') }}</h2>
                <form class="flex max-w-xl flex-wrap gap-2" @submit.prevent="search">
                    <input v-model="query" type="search" maxlength="120" class="input min-w-0 flex-1" :aria-label="t('admin.songVideos.searchLabel')" data-song-videos-query>
                    <button type="submit" class="btn-secondary" :disabled="searching || !query.trim()">{{ searching ? t('admin.loading') : t('admin.songVideos.search') }}</button>
                </form>
                <p class="text-xs text-muted">{{ t('admin.songVideos.searchQuota') }}</p>
                <p v-if="searchError" class="text-sm text-bad" role="alert">{{ searchError }}</p>
                <ul v-if="results" class="space-y-2" data-song-videos-results>
                    <li v-if="!results.length" class="text-sm text-muted">{{ t('admin.songVideos.noResults') }}</li>
                    <li v-for="r in results" :key="r.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-raised p-3">
                        <span class="min-w-0 text-sm"><span class="font-semibold text-cream" translate="no">{{ r.title }}</span> <span class="text-muted">· <span translate="no">{{ r.channel }}</span> · {{ minutes(r.durationSeconds) }}</span></span>
                        <button v-if="r.usable" type="button" class="btn-secondary btn-sm" data-song-videos-use @click="useVideo(r.id)">{{ t('admin.songVideos.use') }}</button>
                        <StatusChip v-else tone="muted" :label="t(HIDDEN_NAMES[r.hiddenReason] ?? HIDDEN_NAMES.not_embeddable)" />
                    </li>
                </ul>
                <form class="flex max-w-xl flex-wrap gap-2" @submit.prevent="useVideo(videoIdFrom(link))">
                    <input v-model="link" type="url" class="input min-w-0 flex-1" :aria-label="t('admin.songVideos.linkLabel')" :placeholder="t('admin.songVideos.linkPlaceholder')" data-song-videos-link>
                    <button type="submit" class="btn-secondary" :disabled="!videoIdFrom(link)">{{ t('admin.songVideos.use') }}</button>
                </form>
            </section>

            <section v-if="video" class="space-y-4" data-song-videos-work>
                <h2 class="text-lg font-bold text-cream">{{ t('admin.songVideos.sync') }}</h2>
                <div class="aspect-video w-full max-w-3xl overflow-hidden rounded-xl bg-raised">
                    <div ref="player" class="h-full w-full"></div>
                </div>
                <p v-if="playerError" class="text-sm text-bad" role="alert" data-song-videos-player-error>{{ playerError }}</p>
                <div ref="laneHost" class="h-56 w-full max-w-3xl overflow-hidden rounded-xl bg-raised" data-song-videos-lane><canvas ref="lane" class="block h-full w-full" aria-hidden="true"></canvas></div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="grid gap-1 text-sm font-semibold text-cream">{{ t('admin.songVideos.part') }}
                        <select v-model="partKey" class="input w-auto">
                            <option v-for="p in template.parts" :key="p.key" :value="p.key">{{ p.name ?? p.key }}</option>
                        </select>
                    </label>
                    <p class="text-sm text-muted" data-song-videos-summary>{{ t('admin.songVideos.summary', { key: keyName, sections: form.filter((s) => s.kind === 'section').length, anchors: anchors.length }) }}</p>
                </div>

                <div class="card space-y-3 p-4">
                    <h3 class="font-bold text-cream">{{ t('admin.songVideos.listenTitle') }}</h3>
                    <p class="max-w-prose text-sm text-muted">{{ t('admin.songVideos.listenHow') }}</p>
                    <!-- The mic that hears the speakers best (the app's own choice, shared with Sing): never a phone's by surprise. -->
                    <label v-if="micChoices().length" class="grid max-w-md gap-1 text-sm font-semibold text-cream">{{ t('admin.songVideos.mic') }}
                        <select class="input" :value="currentMicId() ?? ''" data-song-videos-mic @change="pickMic($event.target.value)">
                            <option v-if="!currentMicId()" value="" disabled>{{ t('admin.songVideos.micAsk') }}</option>
                            <option v-for="m in micChoices()" :key="m.deviceId" :value="m.deviceId" translate="no">{{ m.label }}</option>
                        </select>
                    </label>
                    <div class="flex flex-wrap gap-3">
                        <button v-if="!listening" type="button" class="btn-primary" :disabled="!ready || working" data-song-videos-listen @click="listen">{{ working ? t('admin.songVideos.working') : t('admin.songVideos.listen') }}</button>
                        <button v-else type="button" class="btn-primary" data-song-videos-stop @click="stopListening">{{ t('admin.songVideos.stopListening') }}</button>
                    </div>
                    <!-- While it listens: the mic's level, live, and how much it has heard, so you can see it hears the video. -->
                    <div v-if="listening" class="max-w-md space-y-1" data-song-videos-hearing>
                        <LevelMeter :db="heardDb" segments />
                        <p class="text-sm text-muted tabular-nums">{{ t('admin.songVideos.heardSoFar', { time: minutes(heardSeconds) }) }}</p>
                    </div>
                    <p v-if="said" class="text-sm text-cream" role="status" data-song-videos-said>{{ said }}</p>
                </div>

                <div class="card space-y-3 p-4" data-song-videos-form>
                    <h3 class="font-bold text-cream">{{ t('admin.songVideos.formTitle') }}</h3>
                    <ol class="space-y-2">
                        <li v-for="(s, i) in form" :key="i" class="flex flex-wrap items-end gap-2 text-sm">
                            <template v-if="s.kind === 'section'">
                                <span class="w-28 font-semibold text-cream" translate="no">{{ s.name ?? template.sections[s.section]?.name }}</span>
                                <label class="grid gap-1 text-muted">{{ t('admin.songVideos.shift') }}<input v-model.number="s.shift" type="number" min="-11" max="11" class="input w-20"></label>
                                <label class="grid gap-1 text-muted">{{ t('admin.songVideos.cents') }}<input v-model.number="s.cents" type="number" min="-50" max="50" class="input w-20"></label>
                                <label class="flex min-h-11 items-center gap-2 text-muted"><input type="checkbox" :checked="s.words !== false" @change="s.words = $event.target.checked ? undefined : false">{{ t('admin.songVideos.words') }}</label>
                            </template>
                            <template v-else>
                                <span class="w-28 font-semibold text-cream">{{ t('admin.songVideos.gap') }}</span>
                                <label class="grid gap-1 text-muted">{{ t('admin.songVideos.beats') }}<input v-model.number="s.beats" type="number" min="1" max="400" class="input w-20"></label>
                            </template>
                            <button type="button" class="btn-secondary btn-sm" :aria-label="t('admin.songVideos.removeSegment')" @click="form.splice(i, 1)">×</button>
                        </li>
                    </ol>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary btn-sm" @click="addVerse">{{ t('admin.songVideos.addVerse') }}</button>
                        <button type="button" class="btn-secondary btn-sm" @click="form.push({ kind: 'gap', beats: barBeats })">{{ t('admin.songVideos.addGap') }}</button>
                    </div>
                </div>

                <div class="card space-y-3 p-4" data-song-videos-tap>
                    <h3 class="font-bold text-cream">{{ t('admin.songVideos.fixTitle') }}</h3>
                    <p class="max-w-prose text-sm text-muted">{{ t('admin.songVideos.tapHow') }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button v-if="!tapping" type="button" class="btn-secondary btn-sm" :disabled="!ready || !whole" data-song-videos-tap-start @click="startTapping">{{ t('admin.songVideos.tapFromHere') }}</button>
                        <template v-else>
                            <button type="button" class="btn-primary" data-song-videos-tap-bar @click="tap">{{ t('admin.songVideos.tapBar', { bar: tapping.next + 1 }) }}</button>
                            <button type="button" class="btn-secondary btn-sm" @click="stopTapping">{{ t('admin.songVideos.tapDone') }}</button>
                        </template>
                        <button type="button" class="btn-secondary btn-sm" :disabled="!anchors.length" @click="nudge(-1)">{{ t('admin.songVideos.earlier') }}</button>
                        <button type="button" class="btn-secondary btn-sm" :disabled="!anchors.length" @click="nudge(1)">{{ t('admin.songVideos.later') }}</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <label class="grid gap-1 text-sm font-semibold text-cream">{{ t('admin.songVideos.harmonyLabel') }}
                        <select v-model="harmony" class="input w-auto">
                            <option v-for="h in HARMONIES" :key="h" :value="h">{{ t(HARMONY_NAMES[h]) }}</option>
                        </select>
                    </label>
                    <button type="button" class="btn-primary" :disabled="saving || problems.length > 0" data-song-videos-save @click="save">{{ saving ? t('common.saving') : t('admin.songVideos.save') }}</button>
                </div>
                <p v-if="problems.length && anchors.length" class="text-sm text-muted" data-song-videos-problems>{{ t('admin.songVideos.problems', { problems: problems.join(', ') }) }}</p>
                <p v-if="saveErrors" class="text-sm text-bad" role="alert">{{ Object.values(saveErrors).flat().join(' ') || t('admin.requestFailed') }}</p>
            </section>

            <Modal v-if="removing" :title="t('admin.songVideos.removeTitle')" :dismissable="!busy" @close="removing = null">
                <div class="space-y-5">
                    <p class="text-cream">{{ t('admin.songVideos.removeBody', { title: removing.title }) }}</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" :disabled="busy" @click="removing = null">{{ t('common.cancel') }}</button>
                        <button type="button" class="btn-primary" :disabled="busy" data-song-videos-remove-confirm @click="remove">{{ t('admin.songVideos.removeConfirm') }}</button>
                    </div>
                </div>
            </Modal>
        </div>`,
    methods: {
        videoIdFrom,
        async loadSongs() {
            this.failed = false;
            try {
                const { data } = await api.templates.catalogue();
                this.songs = data.filter((s) => s.reviewed).sort((a, b) => a.title.localeCompare(b.title));
            } catch (error) {
                this.failed = t('admin.requestFailed');
                refused(error);
            }
        },
        async pickSong() {
            this.closeVideo();
            Object.assign(this, { template: null, rows: null, results: null, query: '', form: [], anchors: [] });
            try {
                const { data } = await api.templates.show(this.slug);
                this.template = data.template;
                this.version = data.template.version;
                this.partKey = data.template.parts[0]?.key ?? null;
                this.query = `${data.template.title} choir`;
                this.loadRows();
            } catch (error) {
                this.failed = t('admin.requestFailed');
                refused(error);
            }
        },
        async loadRows() {
            try {
                this.rows = (await api.admin.songVideos.list({ song: this.slug })).data;
            } catch (error) {
                this.rows = [];
                refused(error);
            }
        },
        async search() {
            Object.assign(this, { searching: true, searchError: null });
            try {
                this.results = (await api.admin.songVideos.search(this.query.trim())).data;
            } catch (error) {
                this.searchError = error?.code === 'youtube_unavailable' ? t('admin.songVideos.youtubeUnavailable') : t('admin.requestFailed');
            } finally {
                this.searching = false;
            }
        },
        /** A video to sync: its player opens here (an admin's tap, so loading YouTube now is theirs). */
        async useVideo(id, map = null) {
            if (!id) return;
            this.closeVideo();
            const ticket = (this.ticket = (this.ticket ?? 0) + 1);
            Object.assign(this, {
                video: { id }, playerError: null, ready: false, said: null, saveErrors: null,
                // A plain copy: a row from the list is reactive, which structuredClone refuses.
                form: map?.form ? JSON.parse(JSON.stringify(map.form)) : candidateForms(this.template, 1),
                anchors: map?.anchors ? JSON.parse(JSON.stringify(map.anchors)) : [],
                harmony: map?.harmony ?? 'same',
            });
            await this.$nextTick();
            this.painter = new LanePainter(this.$refs.lane, this.$refs.laneHost);
            this.paintNotes();
            try {
                const player = await openPlayer(this.$refs.player, id, {
                    state: (code) => this.clock?.stateChanged(code),
                    rate: (rate) => this.clock?.rateChanged(rate),
                    error: (why) => (this.playerError = t('admin.songVideos.playerError', { why })),
                });
                // Another video (or song, or none) was asked for while YouTube loaded: this one goes.
                if (ticket !== this.ticket) {
                    player.destroy();
                    return;
                }
                this.player = player;
                this.clock = videoClock(this.player.player);
                this.ready = true;
                this.startLoop();
            } catch (error) {
                this.playerError = t('admin.songVideos.youtubeUnavailable');
                console.error('YouTube player failed', error);
            }
        },
        /** A saved map opens on the song's version it was made against, and saves against it again. */
        async openRow(row) {
            if (row.templateVersion !== this.version) {
                try {
                    const { data } = await api.templates.show(this.slug, { version: row.templateVersion });
                    Object.assign(this, { template: data.template, version: data.template.version });
                } catch (error) {
                    refused(error);
                    return;
                }
            }
            this.useVideo(row.videoId, row);
        },
        closeVideo() {
            this.ticket = (this.ticket ?? 0) + 1;
            this.stopTapping();
            this.stopLoop();
            this.player?.destroy();
            Object.assign(this, { player: null, clock: null, painter: null, ready: false, video: null, tapping: null });
        },
        paintNotes() {
            if (!this.painter || !this.whole) return;
            const part = this.whole.parts.find((p) => p.key === this.partKey) ?? this.whole.parts[0];
            const index = this.whole.parts.indexOf(part);
            const colorOf = (i) => token(`part-${i + 1}`, '#c9b456');
            this.painter.set({
                notes: part.guide.notes,
                words: part.guide.notes.filter((n) => n.lyric).map((n) => ({ beat: n.beat, text: n.lyric })),
                beatSeconds: 60 / this.template.tempo,
                noteName: (m) => nameNote(m),
                keyboard: true,
                secondsShown: SHOWN,
                color: colorOf(index),
                others: this.whole.parts.filter((p) => p !== part).map((p) => ({ key: p.key, name: p.name, notes: p.guide.notes, color: colorOf(this.whole.parts.indexOf(p)) })),
            }).draw();
        },
        // Drawing follows the player's clock, on animation frames; the mic step logs the two clocks side by side.
        startLoop() {
            this.stopLoop();
            const frame = () => {
                this.raf = requestAnimationFrame(frame);
                if (!this.clock) return;
                const seconds = this.clock.read();
                if (this.listening) {
                    this.listening.log.push([engine.ctx.currentTime - this.listening.startedAt, seconds]);
                    this.heardDb = micDb();
                    this.heardSeconds = engine.ctx.currentTime - this.listening.startedAt;
                }
                // Before the first anchor the song waits to come in; after the last it rests on its end.
                const last = this.anchors[this.anchors.length - 1];
                const beat = beatAt(this.anchors, seconds) ?? (last && seconds > last[0] ? last[1] : null);
                if (this.painter && this.whole) this.painter.set({ position: beat === null ? -SHOWN : (beat * 60) / this.template.tempo }).draw();
            };
            this.raf = requestAnimationFrame(frame);
        },
        stopLoop() {
            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = null;
        },
        async listen() {
            this.said = null;
            const mic = await openMic().catch((error) => {
                this.said = t('admin.songVideos.noMic');
                console.error('Mic failed', error);
                return null;
            });
            if (!mic) return;
            const rate = engine.ctx.sampleRate;
            const startedAt = Math.ceil(engine.ctx.currentTime * rate) / rate;
            this.listening = { startedAt, log: [], capture: engine.startCapture(Math.round(startedAt * rate), null, null) };
            this.said = t('admin.songVideos.listeningNow');
        },
        /** Another mic: the app's choice too. Mid-listen, what was heard so far goes, and it starts again from the top. */
        async pickMic(deviceId) {
            const was = !!this.listening;
            if (was) this.dropListening();
            await chooseMic(deviceId);
            if (was) {
                await this.listen();
                this.said = t('admin.songVideos.micChanged');
            }
        },
        dropListening() {
            engine.keepCapture({ interrupted: true });
            closeMic();
            this.listening = null;
        },
        async stopListening() {
            const { log } = this.listening;
            const take = engine.keepCapture({ interrupted: false });
            closeMic();
            this.listening = null;
            if (!take?.samples?.length) {
                this.said = t('admin.songVideos.heardNothing');
                return;
            }
            this.working = true;
            this.said = t('admin.songVideos.working');
            await new Promise((resolve) => requestAnimationFrame(resolve)); // the words show before the sums start
            let heard = hearChroma(take.samples, take.sampleRate);
            take.samples = null; // the heard audio goes as soon as it's turned into chroma
            const found = suggest(this.template, heard, candidateForms(this.template));
            heard = null; // and the chroma once the suggestion is made: only the map is kept
            this.working = false;
            if (!found) {
                this.said = t('admin.songVideos.heardNothing');
                return;
            }
            // What the mic heard came out of the speakers and into the mic late: the device's round trip, measured (or
            // estimated), comes off, so an anchor marks when the video played the bar, not when we heard it.
            const late = (audio.latencyFrames ?? engine.estimatedLatencyFrames()) / engine.ctx.sampleRate;
            const anchors = [];
            for (const [h, beat] of found.anchors) {
                const at = Math.round(toVideo(log, h - late) * 1000) / 1000;
                if (!anchors.length || (at > anchors[anchors.length - 1][0] && beat > anchors[anchors.length - 1][1])) anchors.push([at, beat]);
            }
            this.form = found.form;
            this.anchors = anchors;
            this.said = t('admin.songVideos.suggested', { sections: found.form.filter((s) => s.kind === 'section').length, key: this.keyName, cents: found.cents, anchors: anchors.length });
        },
        addVerse() {
            const n = this.form.filter((s) => s.kind === 'section').length / Math.max(1, this.template.sections.length);
            const last = [...this.form].reverse().find((s) => s.kind === 'section');
            this.template.sections.forEach((_, section) => this.form.push({ kind: 'section', section, shift: last?.shift ?? 0, cents: last?.cents ?? 0, words: false, name: t('admin.songVideos.verseName', { n: Math.floor(n) + 1 }) }));
        },
        /** Tapping bar lines from the bar the video is in now: those before stay, the rest are tapped again. */
        startTapping() {
            const now = this.clock.read();
            const beat = beatAt(this.anchors, now);
            const after = (b) => this.barLines.findIndex((line) => line > b + 1e-6);
            const past = this.anchors.length && now > this.anchors[0][0]; // after the last anchor (before the first: from the top)
            const found = beat === null ? -1 : after(beat);
            const next = found >= 0 ? found : past ? this.barLines.length - 1 : 0;
            this.tapping = { next, kept: this.anchors.filter(([time, b]) => b < this.barLines[next] - 1e-6 && time < now), taps: [] };
            window.addEventListener('keydown', this.onKey);
        },
        onKey(event) {
            if (!this.tapping || event.target?.closest?.('input, textarea, select, [contenteditable]')) return;
            if (event.key === 't' || event.key === 'T') {
                event.preventDefault();
                this.tap();
            }
        },
        tap() {
            const { tapping } = this;
            if (!tapping || tapping.next >= this.barLines.length) return;
            tapping.taps.push([Math.round(this.clock.read() * 1000) / 1000, this.barLines[tapping.next]]);
            tapping.next += 1;
            this.anchors = [...tapping.kept, ...tapping.taps];
            if (tapping.next >= this.barLines.length) this.stopTapping();
        },
        stopTapping() {
            window.removeEventListener('keydown', this.onKey);
            this.tapping = null;
        },
        nudge(direction) {
            this.anchors = this.anchors.map(([time, beat]) => [Math.max(0, Math.round((time + direction * NUDGE) * 1000) / 1000), beat]);
        },
        async save() {
            Object.assign(this, { saving: true, saveErrors: null });
            try {
                await api.admin.songVideos.save({ song: this.slug, templateVersion: this.version, videoId: this.video.id, form: this.form, anchors: this.anchors, harmony: this.harmony, otherWords: null });
                toast(t('admin.songVideos.saved'));
                this.loadRows();
            } catch (error) {
                this.saveErrors = errorsFrom(error) ?? {};
                if (error?.code === 'youtube_unavailable') this.saveErrors = { videoId: [t('admin.songVideos.youtubeUnavailable')] };
                refused(error);
            } finally {
                this.saving = false;
            }
        },
        async approve(row) {
            this.busy = true;
            try {
                await api.admin.songVideos.approve(row.id);
                toast(t('admin.songVideos.approvedToast'));
                this.loadRows();
            } catch (error) {
                refused(error);
            } finally {
                this.busy = false;
            }
        },
        async remove() {
            this.busy = true;
            try {
                await api.admin.songVideos.remove(this.removing.id);
                this.removing = null;
                toast(t('admin.songVideos.removed'));
                this.loadRows();
            } catch (error) {
                refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};
