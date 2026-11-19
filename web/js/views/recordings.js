// Sing with a real recording (docs/youtube-sync-plan.md, "The feature, as a singer sees it"; Hillel, 2026-11-19): a
// library song's synced recordings on YouTube, at /t/<song>/recordings (?r=<id> opens one). Pick one: a card says it plays from YouTube,
// and only its tap loads anything from Google (js/youtube-player.js). Then the video plays at the top, never covered,
// and our lane follows it below, in the recording's key and at its pace (js/sync-map.js over js/video-clock.js), with
// your part lit. The video is the only sound: our guide never plays over it. Sing along turns the mic on for live
// pitch on the lane; nothing is recorded, scored or counted toward XP (YouTube's rules: no rewards for watching, no
// storing its audio). A nudge (remembered on this device) fixes a speaker's or headphones' delay. Free for everyone.

import { api } from '../api.js';
import { t } from '../i18n.js';
import { local } from '../storage.js';
import { token } from '../colors.js';
import { hzToMidi } from '../dsp.js';
import { LanePainter } from '../lane-painter.js';
import { nameNote } from '../note-names.js';
import { transposeKey } from '../staff-layout.js';
import { follow, voiceFollower } from '../livevoice.js';
import { songTitle } from '../song-titles.js';
import { templatePartName } from '../templates.js';
import { beatAt, centsAt, unroll } from '../sync-map.js';
import { videoClock } from '../video-clock.js';
import { openPlayer } from '../youtube-player.js';
import { Icon } from '../components/icon.js';
import { AppScreen } from '../components/page.js';
import { signInTo, signUpFor } from '../components/account-wall.js';

const NUDGE_KEY = 'stackapella.videoNudge';
const NUDGE_STEP = 0.05; // seconds
const NUDGE_MOST = 0.5;
const SHOWN = 6; // seconds across the lane
const INPUT_DELAY = 0.01; // seconds: a mic's own delay, before the browser hears it (a usual built-in mic)
const BACK_JUMP = 1; // seconds: the video moving back this far is a seek, and the voice line starts again
const COUNT_IN = 3; // seconds: before the singing, the lane holds the first notes this far ahead, then brings them in

/** The recording's key, from its first section's shift. */
export function recordingKey(template, form) {
    const first = form.find((s) => s.kind === 'section');
    return transposeKey(template.key, first?.shift ?? 0);
}

/** Whether performance beat `beat` falls where the recording sings words we don't show. */
export function otherWordsAt(ranges, beat) {
    return beat !== null && (ranges ?? []).some(([from, to]) => beat >= from && beat < to);
}

const readNudge = () => {
    const n = Number(local.get(NUDGE_KEY));
    return Number.isFinite(n) ? Math.max(-NUDGE_MOST, Math.min(NUDGE_MOST, n)) : 0;
};

export const RecordingsView = {
    components: { AppScreen, Icon },
    props: { id: { type: String, required: true } },
    data: () => ({
        state: 'loading', song: null, recordings: [], chosen: null, template: null, partKey: null,
        playing: false, loadingPlayer: false, cantFollow: false, playerError: null, nudge: readNudge(), singing: false, micError: null, otherWords: false,
    }),
    computed: {
        signUp() {
            return signUpFor('songs');
        },
        signIn() {
            return signInTo();
        },
        title() {
            return this.song ? songTitle(this.song) : '';
        },
        whole() {
            return this.template && this.chosen ? unroll(this.template, this.chosen.form) : null;
        },
        parts() {
            return (this.template?.parts ?? []).filter((p) => p.kind !== 'instrument');
        },
    },
    watch: {
        partKey() {
            this.paintNotes();
        },
    },
    async created() {
        try {
            const [{ data: shown }, { data: videos }] = await Promise.all([api.templates.show(this.id), api.templates.videos(this.id)]);
            this.song = shown.template;
            this.recordings = videos;
            // A guest has the song's first line only (templates.show's phrase grant): following a whole recording needs
            // the whole song, which a free account has.
            if (shown.grant === 'phrase' && videos.length) {
                this.state = 'guest';
                return;
            }
            this.state = videos.length ? 'list' : 'none';
            // A link to one recording (?r=<id>) opens it, still waiting for Play.
            const asked = videos.find((v) => v.id === new URLSearchParams(location.search).get('r'));
            if (asked) await this.choose(asked);
        } catch (error) {
            console.error('Recordings failed to load', error);
            this.state = 'failed';
        }
    },
    beforeUnmount() {
        this.close();
    },
    template: `
        <AppScreen :title="title || t('recordings.title')" :title-is-content="!!title" :eyebrow="title ? t('recordings.title') : null" content-class="pt-4" data-recordings :data-state="state">
            <p v-if="state === 'loading'" class="mt-6 text-muted" role="status">{{ t('common.loading') }}</p>
            <p v-else-if="state === 'failed'" class="mt-6 text-cream" role="alert">{{ t('recordings.failed') }}</p>
            <p v-else-if="state === 'none'" class="mt-6 text-cream" data-recordings-none>{{ t('recordings.none') }}</p>
            <section v-else-if="state === 'guest'" class="card mt-6 max-w-md space-y-4 p-6" data-recordings-guest>
                <p class="text-cream">{{ t('recordings.guest') }}</p>
                <div class="flex flex-wrap gap-3">
                    <a class="btn-primary" :href="signUp" @click.prevent="go(signUp)">{{ t('recordings.signUp') }}</a>
                    <a class="btn-secondary" :href="signIn" @click.prevent="go(signIn)">{{ t('recordings.signIn') }}</a>
                </div>
            </section>

            <ul v-if="state === 'list' && !chosen" class="mt-6 grid gap-3 md:grid-cols-2" data-recordings-list>
                <li v-for="r in recordings" :key="r.id">
                    <button type="button" class="card flex w-full items-center gap-4 p-4 text-start" :data-recording="r.id" @click="choose(r)">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full glass-bubble text-cream" aria-hidden="true"><Icon name="play" :size="22" /></span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-cream" translate="no">{{ r.title }}</span>
                            <span class="block text-sm text-muted"><span translate="no">{{ r.channel }}</span> · {{ length(r.durationSeconds) }}</span>
                            <span v-if="r.harmony !== 'same'" class="mt-1 block text-sm text-muted" data-recording-harmony>{{ t('recordings.differs') }}</span>
                        </span>
                    </button>
                </li>
            </ul>

            <section v-if="chosen" class="mt-6 space-y-4" data-recording-open>
                <div class="aspect-video w-full overflow-hidden rounded-xl bg-raised" style="min-height: 200px">
                    <p v-if="cantFollow" class="grid h-full place-items-center p-6 text-center text-cream" data-recording-cant>{{ t('recordings.cantFollow') }}</p>
                    <div v-else-if="!playing" class="grid h-full place-items-center p-6 text-center" data-recording-card>
                        <div class="space-y-3">
                            <p class="font-semibold text-cream" translate="no">{{ chosen.title }}</p>
                            <p class="text-sm text-muted">{{ t('recordings.fromYouTube') }}</p>
                            <button type="button" class="btn-primary" :aria-busy="loadingPlayer ? 'true' : 'false'" data-recording-play @click="play">{{ loadingPlayer ? t('common.loading') : t('recordings.play') }}</button>
                        </div>
                    </div>
                    <div v-show="playing" class="h-full w-full"><div ref="player" class="h-full w-full"></div></div>
                </div>
                <p class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted">
                    <span><span translate="no">{{ chosen.channel }}</span> · {{ t('recordings.key', { key: recordingKey(template ?? song, chosen.form) }) }}</span>
                    <a :href="'https://www.youtube.com/watch?v=' + chosen.videoId" target="_blank" rel="noopener" class="link" data-recording-youtube>{{ t('recordings.watchOnYouTube') }}<span aria-hidden="true" class="ms-1">↗</span></a>
                </p>
                <p v-if="playerError" class="text-sm text-bad" role="alert" data-recording-error>{{ playerError }}</p>

                <div ref="laneHost" class="relative h-56 w-full overflow-hidden rounded-xl bg-raised sm:h-64" data-recording-lane><canvas ref="lane" class="block h-full w-full" aria-hidden="true"></canvas>
                    <p v-if="otherWords" class="absolute inset-x-0 top-2 text-center text-xs text-muted" data-recording-other-words>{{ t('recordings.otherWords') }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2" role="radiogroup" :aria-label="t('recordings.yourPart')">
                    <button v-for="p in parts" :key="p.key" type="button" role="radio" class="filter-chip" :aria-checked="partKey === p.key ? 'true' : 'false'" :data-part="p.key" @click="partKey = p.key">{{ templatePartName(p) }}</button>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button v-if="playing" type="button" :class="singing ? 'btn-secondary' : 'btn-primary'" data-recording-sing @click="toggleSing">{{ singing ? t('recordings.stopSinging') : t('recordings.singAlong') }}</button>
                    <span class="flex items-center gap-2 text-sm text-muted">{{ t('recordings.delay') }}
                        <button type="button" class="btn-secondary btn-sm" :aria-label="t('recordings.earlier')" @click="setNudge(-1)">−</button>
                        <span class="min-w-14 text-center tabular-nums text-cream" data-recording-nudge>{{ t('recordings.ms', { ms: Math.round(nudge * 1000) }) }}</span>
                        <button type="button" class="btn-secondary btn-sm" :aria-label="t('recordings.later')" @click="setNudge(1)">+</button>
                    </span>
                </div>
                <p v-if="singing" class="text-sm text-muted">{{ t('recordings.headphones') }}</p>
                <p v-if="micError" class="text-sm text-bad" role="alert">{{ micError }}</p>
                <p class="text-xs text-muted">{{ t('recordings.ours') }}</p>
                <button v-if="recordings.length > 1" type="button" class="btn-secondary btn-sm" @click="close">{{ t('recordings.another') }}</button>
            </section>
        </AppScreen>`,
    methods: {
        recordingKey,
        templatePartName,
        go(path) {
            location.assign(path);
        },
        length(seconds) {
            return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        },
        async choose(recording) {
            this.close();
            Object.assign(this, { chosen: recording, playerError: null, cantFollow: false, template: null });
            let template = this.song;
            try {
                // The map was made against this version of the song: its sections are the ones to follow, never another's.
                if (recording.templateVersion !== this.song.version) template = (await api.templates.show(this.id, { version: recording.templateVersion })).data.template;
            } catch (error) {
                console.error('Recording version failed', error);
                template = null;
            }
            if (this.chosen !== recording) return; // another was chosen (or none) meanwhile
            if (!template || recording.form.some((s) => s.kind === 'section' && !template.sections[s.section])) {
                this.cantFollow = true;
                return;
            }
            this.template = template;
            this.partKey = this.parts.find((p) => p.melody)?.key ?? this.parts[0]?.key ?? null;
            await this.$nextTick();
            if (this.chosen !== recording || !this.$refs.lane) return;
            this.painter = new LanePainter(this.$refs.lane, this.$refs.laneHost);
            this.paintNotes();
            this.painter.set({ position: this.laneSeconds(0) ?? 0 }).draw();
        },
        /** The tap that loads YouTube, then plays (on iOS, YouTube's own play button may be needed once more). */
        async play() {
            if (this.loadingPlayer) return;
            this.loadingPlayer = true; // said in the same frame as the press (docs/design-rules.md, every play control)
            this.playerError = null;
            const ticket = (this.ticket = (this.ticket ?? 0) + 1);
            try {
                const player = await openPlayer(this.$refs.player, this.chosen.videoId, {
                    state: (code) => this.clock?.stateChanged(code),
                    rate: (rate) => this.clock?.rateChanged(rate),
                    error: (why) => this.failed(why),
                });
                if (ticket !== this.ticket) {
                    player.destroy();
                    return;
                }
                this.player = player;
                this.clock = videoClock(player.player, { nudge: this.nudge });
                this.playing = true;
                player.player.playVideo();
                this.loop();
            } catch (error) {
                if (ticket !== this.ticket) return;
                console.error('YouTube failed', error);
                this.failed(error?.why ?? 'unavailable');
            } finally {
                if (ticket === this.ticket) this.loadingPlayer = false;
            }
        },
        failed(why) {
            this.playerError = ['not_embeddable', 'removed', 'bad_id'].includes(why) ? t('recordings.gone') : t('recordings.playerFailed');
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
                others: this.whole.parts.filter((p) => p !== part && p.kind !== 'instrument' && Math.abs(this.whole.parts.indexOf(p) - index) === 1)
                    .map((p) => ({ key: p.key, name: templatePartName(p), notes: p.guide.notes, color: colorOf(this.whole.parts.indexOf(p)), above: this.whole.parts.indexOf(p) < index })),
            });
            this.voice = voiceFollower();
            this.track = [];
        },
        /** Where the lane is at `videoSeconds`: the performance beat's place at our tempo, or before or after the song. */
        laneSeconds(videoSeconds) {
            const anchors = this.chosen.anchors;
            const [first] = anchors;
            const last = anchors[anchors.length - 1];
            const beat = beatAt(anchors, videoSeconds);
            if (beat !== null) return (beat * 60) / this.template.tempo;
            if (last && videoSeconds > last[0]) return (last[1] * 60) / this.template.tempo; // the song's end, resting
            // Before the singing starts (the intro): the first notes wait in view, then come in on the clock.
            return first ? (first[1] * 60) / this.template.tempo - Math.min(COUNT_IN, first[0] - videoSeconds) : null;
        },
        // Drawn on animation frames from the player's clock: nothing here keeps time, and nothing plays.
        loop() {
            if (this.raf) cancelAnimationFrame(this.raf);
            const frame = () => {
                this.raf = requestAnimationFrame(frame);
                if (!this.clock || !this.painter) return;
                const now = this.clock.read();
                if (this.lastNow !== undefined && now < this.lastNow - BACK_JUMP) this.restartVoice(); // a seek back: a fresh line
                this.lastNow = now;
                const seconds = this.laneSeconds(now);
                const beat = seconds === null ? null : (seconds * this.template.tempo) / 60;
                this.otherWords = otherWordsAt(this.chosen.otherWords, beat);
                const shown = seconds ?? 0;
                const voice = (this.track ?? []).filter((p) => p.seconds <= shown && p.seconds > shown - SHOWN).map((p) => ({ ...p, alpha: 1 }));
                this.painter.set({ position: shown, voice, lit: this.voice?.lit ?? new Set(), hits: this.voice?.hits ?? new Map() }).draw();
            };
            this.raf = requestAnimationFrame(frame);
        },
        async toggleSing() {
            if (this.singing) return this.stopSinging();
            this.micError = null;
            this.singing = true;
            this.restartVoice();
            const asked = (this.micAsk = (this.micAsk ?? 0) + 1);
            try {
                const [m, v] = await Promise.all([import('../public/live-mic.js'), import('../public/voices.js')]);
                if (asked !== this.micAsk) return;
                this.micClock = () => v.audio().currentTime;
                this.micWindow = () => 2048 / (v.audio().sampleRate || 48000); // live-mic's analysis window
                const mic = await m.listen({ onFrame: (f) => this.heard(f), cancelEcho: true });
                // Stopped (or asked again) while the browser asked: this mic goes at once.
                if (asked !== this.micAsk) return mic?.stop?.();
                this.mic = mic;
            } catch (error) {
                if (asked !== this.micAsk) return;
                this.singing = false;
                this.micError = t('recordings.noMic');
                console.error('Mic failed', error);
            }
        },
        restartVoice() {
            this.voice = voiceFollower();
            this.track = [];
        },
        /** A mic reading, placed where the recording was when it was heard, and followed as Sing follows a singer. */
        heard(frame) {
            if (!this.singing || !this.clock?.playing || !this.whole) return;
            // How long ago this was sung: the reading's own wait, half its window (its middle), and the mic's own delay, in
            // the video's time (at its speed). The nudge, in clock.read(), is the speaker's side.
            const ago = Math.max(0, (this.micClock?.() ?? frame.at) - frame.at) + (this.micWindow?.() ?? 0.04) / 2 + INPUT_DELAY;
            const seconds = this.laneSeconds(this.clock.read() - ago * (this.player?.player?.getPlaybackRate?.() || 1));
            if (seconds === null || seconds < 0) return;
            const beat = (seconds * this.template.tempo) / 60;
            const part = this.whole.parts.find((p) => p.key === this.partKey) ?? this.whole.parts[0];
            const note = part.guide.notes.find((n) => beat >= n.beat && beat < n.beat + n.length);
            // Judged against the recording's own tuning: a choir 20 cents flat is in tune with itself.
            const hz = frame.hz > 0 ? frame.hz * 2 ** (-centsAt(this.template, this.chosen.form, beat) / 1200) : 0;
            this.voice = follow(this.voice, { seconds, hz, shownHz: hz, loudness: frame.loudness ?? -30 }, note ? { beat: note.beat, pitch: note.pitch } : null);
            if (hz) this.track.push({ seconds, midi: hzToMidi(hz), level: Math.max(0.15, Math.min(1, ((frame.loudness ?? -30) + 50) / 40)) });
            while (this.track.length && this.track[0].seconds < seconds - SHOWN) this.track.shift(); // only what the lane shows
        },
        stopSinging() {
            this.micAsk = (this.micAsk ?? 0) + 1;
            this.singing = false;
            this.mic?.stop?.();
            this.mic = null;
        },
        setNudge(direction) {
            this.nudge = Math.max(-NUDGE_MOST, Math.min(NUDGE_MOST, Math.round((this.nudge + direction * NUDGE_STEP) * 1000) / 1000));
            local.set(NUDGE_KEY, String(this.nudge));
            this.clock?.setNudge(this.nudge);
        },
        close() {
            this.ticket = (this.ticket ?? 0) + 1;
            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = null;
            this.stopSinging();
            this.player?.destroy();
            this.painter?.destroy();
            this.lastNow = undefined;
            Object.assign(this, { player: null, clock: null, painter: null, chosen: null, playing: false, loadingPlayer: false, cantFollow: false, otherWords: false });
        },
    },
};
