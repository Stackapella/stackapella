// Practise the set (docs/practice-playlist-plan.md; Brief H in docs/build-plan-2026-11-23.md): a choir group's songs one
// after another with your part louder (or without it), playing on with the phone locked. The set is rendered on open
// (practice-set-render.js) into ONE file that one media element plays: the installed app refuses a new play() while
// locked (the plan's 3a), so Next and Previous are seeks, never a new src. The lock screen's controls come from Media
// Session. Behind config.practiceSet (the practice_set switch, on for staff until Hillel's iPhone pass).

import { markRaw } from 'vue';
import { api } from '../api.js';
import { engine } from '../audio.js';
import { AppScreen } from '../components/page.js';
import { Segmented } from '../components/controls.js';
import { Icon } from '../components/icon.js';
import { locale, t } from '../i18n.js';
import { navigate } from '../router.js';
import { account, loadAuthConfig } from '../stores/account.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { songTitle } from '../song-titles.js';
import { count } from '../usage.js';
import { clock, estimateMb, isFresh, nextStart, partFor, partSpan, previousStart, setSignature, setSongs, songAt, timeline, upcoming } from '../practice-set.js';
import { keepSet, keptSet, setKey, sweepSets } from '../practice-set-store.js';

const PICKED = (groupId) => `stackapella.setPart.${groupId}`;
const readPick = (groupId) => {
    try {
        return JSON.parse(localStorage.getItem(PICKED(groupId)) || 'null');
    } catch (error) {
        console.warn('The set part could not be read', error);
        return null;
    }
};
const keepPick = (groupId, pick) => {
    try {
        localStorage.setItem(PICKED(groupId), JSON.stringify(pick));
    } catch (error) {
        console.warn('The set part could not be kept', error);
    }
};

const ACTIONS = ['play', 'pause', 'previoustrack', 'nexttrack', 'seekto', 'seekbackward', 'seekforward'];
const SKIP_SECONDS = 10;
// Repeat my part's loop: a moment before the part's first note (to come in), and its last note let ring.
const REPEAT_LEAD = 1;
const REPEAT_TAIL = 0.5;

export const PracticeSetView = {
    components: { AppScreen, Icon, Segmented },
    props: { id: { type: String, required: true } },
    data: () => ({ group: null, failed: null, songs: [], skipped: 0, pick: null, phase: 'loading', progress: 0, url: null, starts: [], total: 0, at: 0, playing: false, started: false, paused: false, kind: 'louder', repeat: null, events: [], choice: 'all', fromEvent: false, confirmed: false, sizeMb: 0 }),
    setup: () => ({ clock }),
    computed: {
        /** The parts to pick from when a song has no part of yours (in the order the songs have them, each once). */
        partChoices() {
            const seen = new Map();
            for (const song of this.songs) for (const p of song.template.parts) if (p.kind !== 'instrument' && !seen.has(p.key)) seen.set(p.key, { value: p.key, label: templatePartName(p), name: p.name ?? p.key });
            return [...seen.values()];
        },
        needsPick() {
            return this.songs.some((s) => !s.assigned);
        },
        index() {
            return songAt(this.starts, this.at);
        },
        current() {
            return this.songs[this.index] ?? null;
        },
        songSeconds() {
            return this.current?.seconds ?? 0;
        },
        songAtSeconds() {
            return Math.max(0, Math.min(this.songSeconds, this.at - (this.starts[this.index] ?? 0)));
        },
        /** Repeat my part's stretch in the set (seconds in the one file) for the song repeating, or null. */
        repeatSpan() {
            const song = this.songs[this.repeat];
            const span = song?.partKey ? partSpan(song.template, song.partKey, song.beatZero ?? 0) : null;
            if (!span) return null;
            const start = this.starts[this.repeat] ?? 0;
            return [Math.max(start, start + span[0] - REPEAT_LEAD), Math.min(start + song.seconds, start + span[1] + REPEAT_TAIL)];
        },
        /** Which set: each rehearsal or concert to come (its day, then its name: a long name cut on a phone keeps the day), then all the choir's songs. */
        setChoices() {
            const day = new Intl.DateTimeFormat(locale.lang, { weekday: 'short', day: 'numeric', month: 'short' });
            return [...this.events.map((e) => ({ value: e.id, label: `${day.format(new Date(e.startsAt))} · ${e.name}` })), { value: 'all', label: t('practiceSet.allSongs') }];
        },
        event() {
            return this.events.find((e) => e.id === this.choice) ?? null;
        },
        soundOptions() {
            return [
                { value: 'louder', label: t('practiceSet.sound.louder') },
                { value: 'without', label: t('practiceSet.sound.without') },
            ];
        },
    },
    watch: {
        index() {
            this.showOnLockScreen();
        },
    },
    async mounted() {
        // Opened straight from its address (a reload, the lock screen bringing the app back): the switch once it's known.
        await loadAuthConfig().catch((error) => console.warn('The config did not load for the set', error));
        if (!account.config?.practiceSet) {
            navigate(`/groups/${this.id}`, { replace: true });
            return;
        }
        try {
            this.group = (await api.groups.show(this.id)).data;
        } catch (error) {
            console.warn('The group did not load for the set', error);
            this.failed = t(error?.code === 'offline' ? 'common.offline' : 'practiceSet.failed');
            this.phase = 'failed';
            return;
        }
        sweepSets(); // sets kept past their 14 days go
        // The group's rehearsals and concerts to come (a group linked to Event Schedule; none otherwise, or while it's away).
        try {
            this.events = upcoming((await api.groups.rehearsals.list({ params: { id: this.id } })).data?.events ?? []).slice(0, 4);
        } catch (error) {
            console.warn('The rehearsals did not load for the set', error);
        }
        const asked = new URLSearchParams(location.search).get('event');
        this.choice = this.events.some((e) => e.id === asked) ? asked : this.events[0]?.id ?? 'all';
        await this.gather();
        this.start();
    },
    beforeUnmount() {
        this.$refs.audio?.pause();
        this.clearLockScreen();
        if (this.url) URL.revokeObjectURL(this.url);
        try {
            if ('audioSession' in navigator) navigator.audioSession.type = 'auto';
        } catch (error) {
            console.warn('audioSession.type could not be reset', error);
        }
    },
    methods: {
        t,
        /** The chosen set's songs (setSongs: a rehearsal's own in its order, else all), each song's template loaded. */
        async gather() {
            this.templates ??= new Map();
            const me = this.group.members.find((m) => m.id === account.me?.id);
            const mine = (templateId) => me?.assignments.find((a) => a.templateId === templateId)?.partKey ?? null;
            const { list, fromEvent } = setSongs(this.group.songs, this.event, mine);
            this.fromEvent = fromEvent;
            this.skipped = 0;
            const songs = [];
            for (const { song, assigned } of list) {
                // A song that needs a seat the singer hasn't got isn't theirs to practise yet (it says so in a line).
                if (song.seat !== 'seated') {
                    this.skipped++;
                    continue;
                }
                try {
                    if (!this.templates.has(song.templateId)) this.templates.set(song.templateId, markRaw(await loadTemplate(song.templateId)));
                    songs.push({ templateId: song.templateId, title: songTitle({ id: song.templateId, title: song.title }), template: this.templates.get(song.templateId), assigned, partKey: null, seconds: 0, ai: false });
                } catch (error) {
                    console.warn('A set song did not load', song.templateId, error);
                    this.skipped++;
                }
            }
            this.songs = songs;
            this.at = 0;
        },
        /** Their part asked when a song has none of theirs (once for the group), else the set made. */
        start() {
            if (!this.songs.length) {
                this.phase = 'empty';
                return;
            }
            this.pick = readPick(this.id);
            if (this.needsPick && !this.partChoices.some((c) => c.value === this.pick?.key)) {
                this.pick = null;
                this.phase = 'pick';
                return;
            }
            this.make();
        },
        async chooseSet(value) {
            if (value === this.choice) return;
            this.$refs.audio?.pause();
            this.choice = value;
            this.phase = 'loading';
            await this.gather();
            this.start();
        },
        choosePart(key) {
            const choice = this.partChoices.find((c) => c.value === key);
            this.pick = { key, name: choice?.name ?? key };
            keepPick(this.id, this.pick);
            this.make();
        },
        partName(song) {
            const part = song.template.parts.find((p) => p.key === song.partKey);
            return part ? templatePartName(part) : null;
        },
        /** Renders the set (every song, in order, into one file) and loads it, ready to play. */
        async make() {
            const audio = this.$refs.audio;
            audio?.pause();
            const back = this.starts.length === this.songs.length ? Math.min(this.index, this.songs.length - 1) : 0; // the song they were on, when the sound changes
            this.phase = 'loading';
            this.starts = [];
            this.progress = 0;
            this.started = false;
            this.repeat = null;
            for (const song of this.songs) song.partKey = partFor(song.template, song.assigned, this.pick);
            try {
                const render = await import('../practice-set-render.js');
                // Kept on this device from last time (slice 4): the same songs, parts and sound open at once.
                const key = setKey(this.id, this.kind);
                const sig = setSignature(this.songs, this.kind, `${render.SAMPLE_RATE}:${render.KBPS}`);
                const kept = await keptSet(key);
                let made = isFresh(kept, sig) ? { blob: kept.blob, songs: kept.songs } : null;
                if (!made) {
                    // On a phone, its size first and a tap to make it (never on mobile data unasked); "Not now" goes back.
                    if (this.onPhone() && !this.confirmed) {
                        this.sizeMb = estimateMb(this.songs.map((s) => s.template), render.KBPS);
                        this.phase = 'confirm';
                        return;
                    }
                    this.phase = 'making';
                    made = await render.renderSet(this.songs.map((s) => ({ template: s.template, partKey: s.partKey })), { kind: this.kind, onProgress: (p) => (this.progress = p) });
                    keepSet({ key, sig, blob: made.blob, songs: made.songs });
                }
                made.songs.forEach((m, i) => Object.assign(this.songs[i], { seconds: m.seconds, beatZero: m.beatZero, ai: m.ai }));
                ({ starts: this.starts, total: this.total } = timeline(this.songs));
                if (this.url) URL.revokeObjectURL(this.url);
                this.url = URL.createObjectURL(made.blob);
                this.phase = 'ready';
                await this.$nextTick();
                this.$refs.audio.src = this.url;
                this.at = this.starts[back] ?? 0;
                if (this.at) this.$refs.audio.currentTime = this.at;
                this.showOnLockScreen();
            } catch (error) {
                console.warn('The set could not be made', error);
                this.failed = error?.code === 'offline' ? t('common.offline') : t('practiceSet.failed');
                this.phase = 'failed';
            }
        },
        onPhone() {
            return !!globalThis.matchMedia?.('(pointer: coarse)').matches;
        },
        confirmMake() {
            this.confirmed = true;
            this.make();
        },
        notNow() {
            navigate(`/groups/${this.id}`);
        },
        setKind(kind) {
            if (kind === this.kind) return;
            this.kind = kind;
            this.make();
        },
        toggle() {
            if (this.playing) this.pause();
            else this.play();
        },
        /** Play, from a tap or the lock screen (both gestures). After a pause the same file is loaded again where it was:
         *  the installed app's paused audio session drops its output, so a bare play() moves the clock in silence (3a). */
        play() {
            const audio = this.$refs.audio;
            if (!audio || !this.url) return;
            try {
                if ('audioSession' in navigator) navigator.audioSession.type = 'playback';
            } catch (error) {
                console.warn('audioSession.type could not be set', error);
            }
            if (engine.playing) engine.stop(); // the app's own player, if a song was playing elsewhere
            if (audio.ended) this.at = 0;
            if (this.paused) {
                const at = this.at;
                audio.src = `${this.url}#t=${at.toFixed(2)}`;
                audio.addEventListener('loadedmetadata', () => {
                    if (Math.abs(audio.currentTime - at) > 0.5) audio.currentTime = at;
                }, { once: true });
            } else if (audio.ended) audio.currentTime = 0;
            audio.play().catch((error) => console.warn('The set did not start', error));
            if (!this.started) count('set_play');
            this.started = true;
        },
        pause() {
            this.$refs.audio?.pause();
        },
        seek(seconds) {
            const audio = this.$refs.audio;
            if (!audio) return;
            this.at = Math.max(0, Math.min(this.total, seconds));
            audio.currentTime = this.at;
            this.showPosition();
        },
        /** Repeat my part: round this song's stretch of their part, until a second tap (or another song). Checked on the
         *  element's own time (timeupdate): one file, so no sample accuracy is needed, and nothing is timed by a timer. */
        toggleRepeat() {
            if (this.repeat !== null) {
                this.repeat = null;
                return;
            }
            this.repeat = this.index;
            count('set_repeat');
            const span = this.repeatSpan;
            if (span && (this.at < span[0] || this.at >= span[1])) this.seek(span[0]);
            if (!this.playing) this.play();
        },
        next() {
            this.repeat = null;
            const to = nextStart(this.starts, this.at);
            if (to !== null) this.seek(to);
        },
        previous() {
            this.repeat = null;
            this.seek(previousStart(this.starts, this.at));
        },
        jumpTo(i) {
            this.repeat = null;
            this.seek(this.starts[i] ?? 0);
            if (!this.playing) this.play();
        },
        onTime() {
            this.at = this.$refs.audio?.currentTime ?? 0;
            const span = this.repeatSpan;
            if (span && (this.at >= span[1] || this.at < span[0] - 0.25)) this.seek(span[0]);
        },
        onPlay() {
            this.playing = true;
            this.paused = false;
            this.showPosition();
        },
        onPause() {
            this.playing = false;
            this.paused = !this.$refs.audio?.ended;
            this.showPosition();
        },
        onEnded() {
            this.playing = false;
            this.paused = false;
        },
        /** The lock screen and headphones: the song, your part and the group, and every control (Media Session). */
        showOnLockScreen() {
            const session = navigator.mediaSession;
            const song = this.current;
            if (!session || !song || this.phase !== 'ready') return;
            try {
                const part = this.partName(song);
                session.metadata = new MediaMetadata({
                    title: song.title,
                    artist: part ? t('practiceSet.lockLine', { part, group: this.group.name }) : this.group.name,
                    album: this.fromEvent ? this.event.name : t('practiceSet.title'),
                    artwork: [{ src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' }],
                });
                const handlers = {
                    play: () => this.play(),
                    pause: () => this.pause(),
                    previoustrack: () => this.previous(),
                    nexttrack: () => this.next(),
                    // The lock screen's bar is this song's: its times are the song's own.
                    seekto: (details) => this.seek((this.starts[this.index] ?? 0) + (details.seekTime ?? 0)),
                    seekbackward: (details) => this.seek(this.at - (details.seekOffset || SKIP_SECONDS)),
                    seekforward: (details) => this.seek(this.at + (details.seekOffset || SKIP_SECONDS)),
                };
                for (const action of ACTIONS) {
                    try {
                        session.setActionHandler(action, handlers[action]);
                    } catch (error) {
                        console.warn('A lock screen control is not supported here', action, error);
                    }
                }
                this.showPosition();
            } catch (error) {
                console.warn('The lock screen could not show the set', error);
            }
        },
        showPosition() {
            const session = navigator.mediaSession;
            if (!session || this.phase !== 'ready') return;
            try {
                session.playbackState = this.playing ? 'playing' : 'paused';
                if (this.songSeconds) session.setPositionState?.({ duration: this.songSeconds, playbackRate: 1, position: this.songAtSeconds });
            } catch (error) {
                console.warn('The lock screen position could not be set', error);
            }
        },
        clearLockScreen() {
            const session = navigator.mediaSession;
            if (!session) return;
            try {
                session.metadata = null;
                session.playbackState = 'none';
                for (const action of ACTIONS) session.setActionHandler(action, null);
            } catch (error) {
                console.warn('The lock screen could not be cleared', error);
            }
        },
    },
    template: `
        <AppScreen :title="t('practiceSet.title')" :back="'/groups/' + id" :eyebrow="group?.name ?? null" eyebrow-is-content data-ui="practice-set">
            <div class="space-y-6" data-practice-set>
                <p v-if="phase === 'failed'" class="alert-inline text-[15px]" style="--alert-c: var(--color-bad)" role="alert"><span>{{ failed }}</span></p>
                <button v-if="phase === 'failed' && songs.length" type="button" class="btn-secondary" data-set-retry @click="make">{{ t('common.tryAgain') }}</button>
                <p v-if="phase === 'loading'" class="text-muted" role="status">{{ t('create.loading') }}</p>
                <p v-if="phase === 'empty'" class="text-muted" data-set-empty>{{ t('practiceSet.empty') }}</p>

                <p v-if="songs.length && phase !== 'failed'" class="t-lead">{{ t('practiceSet.lead', { count: songs.length }) }}</p>

                <!-- Which set (a group linked to Event Schedule): the next rehearsal by default, in its order; a later one; or
                     all the choir's songs. A rehearsal with no songs picked plays them all, and says so. -->
                <div v-if="events.length" class="space-y-2" data-set-which>
                    <label for="set-which" class="block text-sm font-semibold text-muted">{{ t('practiceSet.which') }}</label>
                    <select id="set-which" class="input min-h-12" :value="choice" :disabled="phase === 'making' || phase === 'loading'" @change="chooseSet($event.target.value)">
                        <option v-for="c in setChoices" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                    <p v-if="event && !fromEvent" class="t-hint" data-set-none-picked>{{ t('practiceSet.noneForEvent') }}</p>
                </div>

                <!-- Their part, asked once for the group when a song has none of theirs (a director's assignment wins). -->
                <section v-if="phase === 'pick'" class="card space-y-4 p-5" aria-labelledby="set-part" data-set-pick>
                    <h2 id="set-part" class="t-section">{{ t('practiceSet.part.title') }}</h2>
                    <p class="t-hint">{{ t('practiceSet.part.hint') }}</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button v-for="c in partChoices" :key="c.value" type="button" class="btn-secondary min-h-12 justify-center" :data-set-part="c.value" @click="choosePart(c.value)">{{ c.label }}</button>
                    </div>
                </section>

                <!-- A phone: the set's size first, and nothing made (or downloaded) until the tap. -->
                <section v-if="phase === 'confirm'" class="card space-y-4 p-5" aria-labelledby="set-confirm" data-set-confirm>
                    <h2 id="set-confirm" class="t-section">{{ t('practiceSet.confirm.title') }}</h2>
                    <p class="text-muted" data-set-size>{{ t('practiceSet.confirm.text', { mb: sizeMb }) }}</p>
                    <div class="flex flex-wrap gap-2.5">
                        <button type="button" class="btn-primary min-h-12 flex-[1_1_160px] justify-center" data-set-go @click="confirmMake">{{ t('practiceSet.confirm.go') }}</button>
                        <button type="button" class="btn-secondary min-h-12 flex-[1_1_160px] justify-center" data-set-not-now @click="notNow">{{ t('practiceSet.confirm.notNow') }}</button>
                    </div>
                </section>

                <section v-if="phase === 'making'" class="card space-y-3 p-5" role="status" data-set-making>
                    <p class="flex items-center gap-3 font-semibold text-cream"><span class="spinner size-5" aria-hidden="true"></span>{{ t('practiceSet.making', { count: songs.length }) }}</p>
                    <div class="h-1.5 overflow-hidden rounded-full bg-raised" aria-hidden="true"><div class="h-full rounded-full bg-accent transition-[width]" :style="{ width: Math.round(progress * 100) + '%' }"></div></div>
                    <p class="t-hint">{{ t('practiceSet.makingHint') }}</p>
                </section>

                <!-- One element plays the whole set (kept in the page even while it renders, so it's there to load). -->
                <audio ref="audio" preload="auto" class="hidden" data-set-audio @timeupdate="onTime" @seeked="onTime" @play="onPlay" @pause="onPause" @ended="onEnded"></audio>

                <template v-if="phase === 'ready' && current">
                    <div class="card flex flex-col gap-[18px] p-5" data-set-player>
                        <div class="min-w-0">
                            <p class="t-hint">{{ t('practiceSet.songOf', { n: index + 1, total: songs.length }) }}</p>
                            <h2 class="flex flex-wrap items-center gap-2 text-[22px] font-semibold leading-tight text-cream" translate="no" data-set-now>{{ current.title }}<span v-if="current.ai" class="chip" translate="yes">{{ t('practiceSet.ai') }}</span></h2>
                            <p class="text-muted" data-set-part-now>{{ partName(current) ? t('groups.yourPart', { part: partName(current) }) : t('practiceSet.allParts') }}</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="h-1.5 overflow-hidden rounded-full bg-raised" aria-hidden="true" data-set-progress>
                                <div class="h-full rounded-full bg-accent" :style="{ width: songSeconds ? Math.min(100, (songAtSeconds / songSeconds) * 100) + '%' : '0%' }"></div>
                            </div>
                            <div class="flex justify-between text-[13px] text-subtle tabular-nums"><span>{{ clock(songAtSeconds) }}</span><span>{{ clock(songSeconds) }}</span></div>
                        </div>
                        <div class="flex items-center justify-center gap-5">
                            <button type="button" class="grid size-12 place-items-center rounded-full bg-raised text-cream transition hover:brightness-110" data-set-previous @click="previous">
                                <Icon name="rewind" :size="20" /><span class="sr-only">{{ t('practiceSet.previous') }}</span>
                            </button>
                            <button type="button" class="grid size-[64px] place-items-center rounded-full bg-cream text-ink transition hover:brightness-110" data-set-play @click="toggle">
                                <Icon :name="playing ? 'pause' : 'play'" :size="24" /><span class="sr-only">{{ playing ? t('practiceSet.pause') : t('practiceSet.play') }}</span>
                            </button>
                            <button type="button" class="grid size-12 place-items-center rounded-full bg-raised text-cream transition hover:brightness-110 disabled:opacity-40" :disabled="index >= songs.length - 1" data-set-next @click="next">
                                <Icon name="skip" :size="20" /><span class="sr-only">{{ t('practiceSet.next') }}</span>
                            </button>
                        </div>
                        <!-- Repeat my part (only where the song has a part of theirs): a second tap goes back to the whole set. -->
                        <div v-if="current.partKey" class="flex flex-col items-center gap-2">
                            <button type="button" class="btn-secondary min-h-11 justify-center gap-2" :class="repeat === index ? 'border-cream bg-cream text-ink hover:bg-cream' : ''" :aria-pressed="repeat === index ? 'true' : 'false'" data-set-repeat @click="toggleRepeat"><Icon name="repeat" :size="18" />{{ t('practiceSet.repeat') }}</button>
                            <p v-if="repeat === index" class="t-hint text-center" role="status" data-set-repeat-on>{{ t('practiceSet.repeatOn') }}</p>
                        </div>
                        <Segmented :model-value="kind" :options="soundOptions" :label="t('practiceSet.sound.label')" full class="[&_.seg-option]:min-h-11" data-set-sound @update:model-value="setKind" />
                    </div>

                    <section class="space-y-3" aria-labelledby="set-songs">
                        <h2 id="set-songs" class="t-section">{{ t('practiceSet.songs') }}</h2>
                        <ol class="card divide-y divide-line overflow-hidden p-0">
                            <li v-for="(song, i) in songs" :key="song.templateId">
                                <button type="button" class="flex min-h-14 w-full items-center gap-3 px-4 py-2 text-start hover:bg-raised/60" :class="i === index ? 'bg-raised' : ''" :aria-current="i === index ? 'true' : null" :data-set-song="song.templateId" @click="jumpTo(i)">
                                    <span class="w-5 shrink-0 text-sm text-subtle tabular-nums">{{ i + 1 }}</span>
                                    <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-cream" translate="no">{{ song.title }}</span><span class="block text-sm text-muted">{{ partName(song) ?? t('practiceSet.allParts') }}</span></span>
                                    <span class="shrink-0 text-sm text-subtle tabular-nums">{{ clock(song.seconds) }}</span>
                                </button>
                            </li>
                        </ol>
                    </section>
                </template>

                <p v-if="skipped && phase !== 'loading'" class="t-hint" data-set-skipped>{{ t('practiceSet.skipped', { count: skipped }) }}</p>
            </div>
        </AppScreen>`,
};
