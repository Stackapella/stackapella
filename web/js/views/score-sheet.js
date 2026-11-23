// A library song's sheet music, to print and download (docs/song-sheet-music-plan.md): where a song page's
// "Download · free with an account" links land (/t/<song>/sheet?get=pdf|parts|musicxml|midi). A guest is invited to
// make a free account and comes back to the same link; signed in, the song is engraved on its page (js/engrave.js, the
// score the song page shows), the full score or one part, and what the link asked for happens once: the browser's print
// (Print, or save as a PDF) or the file's download. A song withheld where the singer is says so, as its sheet does
// elsewhere in the app (the API refuses it), to a guest too, before any invitation. Black on white, and only the page
// prints.

import { engrave, glyphDefs } from '../engrave.js';
import { t } from '../i18n.js';
import { rightsOf } from '../musicxml.js';
import { goBack, navigate } from '../router.js';
import { sheetAsk, sheetFile, sheetPart, writtenParts } from '../sheet-files.js';
import { songTitle } from '../song-titles.js';
import { account, loadAccount, loadAuthConfig } from '../stores/account.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { notHereLine, withheldSongs } from '../withheld.js';
import { signInTo, signUpFor } from '../components/account-wall.js';

const PAPER = { background: '#fff', color: '#000', fontFamily: "Georgia, 'Times New Roman', Times, serif" }; // i18n-ignore: paper, and the words' face

export const ScoreSheetView = {
    props: { id: { type: String, required: true } },
    setup: () => ({ PAPER }),
    data: () => ({ state: 'loading', song: null, part: null, notHere: '', sent: '', onPaper: false, ask: sheetAsk(location.search) }),
    template: `
        <div class="min-h-screen px-4 py-6" data-print-page data-score-sheet :data-state="state">
            <div v-if="!onPaper" class="mx-auto mb-4 flex max-w-3xl flex-wrap items-center gap-3" data-no-print>
                <button type="button" class="btn-secondary" @click="goBack('/')">{{ t('common.back') }}</button>
                <button v-if="state === 'ready'" type="button" class="btn-primary ms-auto" data-score-print @click="print">{{ t('print.print') }}</button>
            </div>
            <section v-if="state === 'guest'" class="mx-auto mt-10 max-w-md rounded-2xl bg-panel p-6 text-center" data-score-ask>
                <h1 class="text-2xl font-bold text-cream">{{ t('score.askTitle') }}</h1>
                <p class="mt-3 text-cream">{{ t('score.ask') }}</p>
                <div class="mt-6 flex flex-col gap-3">
                    <a class="btn-primary" :href="signUp" data-score-signup @click.prevent="go(signUp)">{{ t('score.signUp') }}</a>
                    <a class="btn-secondary" :href="signIn" @click.prevent="go(signIn)">{{ t('score.signIn') }}</a>
                </div>
            </section>
            <p v-else-if="state === 'withheld'" class="mx-auto max-w-3xl text-cream" role="status" data-score-withheld>{{ notHere }}</p>
            <div v-else-if="state === 'failed'" class="mx-auto max-w-3xl" role="status">
                <p class="text-cream">{{ t('score.failed') }}</p>
                <button type="button" class="btn-secondary mt-3" @click="load">{{ t('score.retry') }}</button>
            </div>
            <template v-else-if="state === 'ready'">
                <div v-if="!onPaper" class="mx-auto mb-4 max-w-3xl" data-no-print>
                    <div class="flex flex-wrap items-center gap-2" role="group" :aria-label="t('score.which')">
                        <button type="button" class="filter-chip" :aria-pressed="String(part === null)" data-score-part="" @click="show(null)">{{ t('score.full') }}</button>
                        <button v-for="p in parts" :key="p.key" type="button" class="filter-chip" :aria-pressed="String(part === p.key)" :data-score-part="p.key" @click="show(p.key)">{{ nameOf(p.key) }}</button>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-secondary" data-score-file="musicxml" @click="download('musicxml')">{{ t('score.musicxml') }}</button>
                        <button type="button" class="btn-secondary" data-score-file="midi" @click="download('midi')">{{ t('score.midi') }}</button>
                    </div>
                    <p class="mt-3 text-sm text-muted">{{ t('print.hint') }}</p>
                    <p v-if="sent" class="mt-2 text-sm text-cream" role="status" data-score-sent>{{ t('score.sent', { file: sent }) }}</p>
                </div>
                <article class="mx-auto max-w-3xl rounded-lg px-6 py-8" :style="PAPER" translate="no" data-score-paper>
                    <header class="mb-2 text-center">
                        <h1 class="text-3xl" :style="{ fontFamily: PAPER.fontFamily, fontWeight: 400, textShadow: 'none', color: '#000' }">{{ title }}</h1>
                        <p class="mt-1 text-sm font-semibold">{{ part ? nameOf(part) : t('score.full') }}</p>
                    </header>
                    <p class="flex items-end justify-between gap-4 text-sm"><span>{{ words }}</span><span class="text-end">{{ tune }}<br v-if="tune">{{ t('score.arranged') }}</span></p>
                    <!-- The engraver's own SVG (its words escaped there), a system a block so none splits over two pages -->
                    <div ref="systems" data-score-systems></div>
                    <p v-if="rights" class="mt-4 text-center text-xs">{{ rights }}</p>
                </article>
            </template>
        </div>`,
    computed: {
        here: () => location.pathname + location.search,
        signUp() {
            return signUpFor('score', this.here);
        },
        signIn() {
            return signInTo(this.here);
        },
        parts() {
            return writtenParts(this.song);
        },
        title() {
            return songTitle(this.song);
        },
        words() {
            return this.song.credits?.words ? t('print.wordsBy', { name: this.song.credits.words }) : '';
        },
        tune() {
            return this.song.credits?.tune ? t('print.tuneBy', { name: this.song.credits.tune }) : '';
        },
        rights() {
            return rightsOf(this.song);
        },
    },
    async created() {
        this.paper = window.matchMedia('print');
        this.onPrint = () => (this.onPaper = this.paper.matches);
        this.paper.addEventListener('change', this.onPrint);
        await this.load();
    },
    unmounted() {
        this.paper?.removeEventListener('change', this.onPrint);
    },
    methods: {
        goBack,
        go(to) {
            navigate(to);
        },
        nameOf(key) {
            return templatePartName(this.song.parts.find((p) => p.key === key));
        },
        async load() {
            this.state = 'loading';
            await loadAccount().catch(() => {});
            // A guest: the invitation, and back here once there's an account. Nothing is read or made before it, except
            // for a song withheld where they are (auth.config's list, public): that is said first, as no account would
            // open it here (the coordinator, 2026-11-17). Its refusal gives the line, with the year it opens here.
            if (!account.me) {
                const config = await loadAuthConfig().catch(() => null);
                // The public list decides; the song's own refusal only adds the year it opens here. Any other answer (a slow or
                // failed request, a copy kept on this device) still says it isn't here, never an invitation (score_sheet's flake).
                if (withheldSongs(config).has(this.id)) {
                    this.notHere = (await loadTemplate(this.id).then(() => null, (error) => notHereLine(error))) || t('templates.notHere');
                }
                this.state = this.notHere ? 'withheld' : 'guest';
                return;
            }
            try {
                this.song = await loadTemplate(this.id);
            } catch (error) {
                // Withheld where the singer is (docs/geo-rights-plan.md): no score and no file, and why.
                this.notHere = notHereLine(error) ?? '';
                this.state = this.notHere ? 'withheld' : 'failed';
                return;
            }
            this.part = sheetPart(this.song, this.ask);
            this.state = 'ready';
            document.title = this.title;
            await this.draw();
            // What the link asked for, once (a test's ?auto=0 leaves it to the buttons).
            if (new URLSearchParams(location.search).get('auto') === '0') return;
            if (this.ask.get === 'musicxml' || this.ask.get === 'midi') this.download(this.ask.get);
            else if (this.ask.get) {
                await document.fonts?.ready;
                this.print();
            }
        },
        /** The page's systems: every part, or one part's own sheet. */
        async draw() {
            await this.$nextTick();
            if (!document.getElementById('eg-gClef')) document.body.insertAdjacentHTML('beforeend', glyphDefs());
            const names = (key, first) => (this.part ? '' : first ? this.nameOf(key) : `${[...this.nameOf(key)][0]}.`);
            const made = engrave(this.song, { partKeys: this.part ? [this.part] : null, names: this.part ? 14 : true, nameOf: names });
            this.$refs.systems.innerHTML = made.systems.map((sys) => `<div data-print-system style="margin-top:10px">${sys.svg}</div>`).join('');
        },
        show(key) {
            this.part = key;
            this.draw();
        },
        print() {
            window.print();
        },
        download(format) {
            const file = sheetFile(this.song, format, this.title);
            const url = URL.createObjectURL(new Blob([file.data], { type: file.type }));
            const link = Object.assign(document.createElement('a'), { href: url, download: file.name });
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 60000); // once the browser has it (a page timer, never audio), as the studio's Export does
            this.sent = file.name;
        },
    },
};
