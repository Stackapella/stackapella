// Printable parts (docs/sheet-music-plan.md, slice 2), from Export's Sheet music, Print: the song
// as the studio sings it (its key and speed), every part in score order or one part with the tune
// greyed above, laid out by js/print-score.js and drawn here as SVG, a system at a time. Black on
// white in both skins, as paper is. It opens the browser's print at once (Print or save as a PDF);
// Print again and Back stay on the page, and only the score prints.

import { t } from '../i18n.js';
import { asSung, rightsOf } from '../musicxml.js';
import { printLayout } from '../print-score.js';
import { ScoreSystem } from '../components/score-system.js';
import { goBack } from '../router.js';
import { shownTitle, storedProject } from '../stores/projects.js';
import { templatePartName } from '../templates.js';

const MUSIC_FONT = "'Noto Music', 'Apple Symbols', 'Segoe UI Symbol', serif"; // i18n-ignore: fonts with the clefs
const PAPER = { background: '#fff', color: '#000' }; // paper, whatever the skin

export const PrintView = {
    props: { id: { type: String, required: true } },
    components: { ScoreSystem },
    setup: () => ({ MUSIC_FONT, PAPER }),
    data: () => ({ song: null, layout: null, title: '', failed: false, partKeys: null, onPaper: false }),
    template: `
        <div class="min-h-screen px-4 py-6" data-print-page>
            <!-- On paper only the score: the page hides these itself (and app.css's print rules too) -->
            <div v-if="!onPaper" class="mx-auto mb-4 flex max-w-3xl flex-wrap items-center gap-3" data-no-print>
                <button type="button" class="btn-secondary" @click="goBack('/p/' + id)">{{ t('common.back') }}</button>
                <button v-if="layout" type="button" class="btn-primary ms-auto" @click="print">{{ t('print.print') }}</button>
                <p v-if="layout" class="basis-full text-sm text-muted">{{ t('print.hint') }}</p>
            </div>
            <p v-if="failed" class="mx-auto max-w-3xl text-cream" role="status">{{ t('print.missing') }}</p>
            <article v-if="layout" class="mx-auto max-w-3xl rounded-lg px-6 py-8" :style="PAPER" translate="no">
                <header class="mb-4 text-center">
                    <h1 class="text-2xl font-bold">{{ title }}</h1>
                    <p v-if="credits" class="mt-1 text-sm">{{ credits }}</p>
                    <p v-if="partLine" class="mt-1 text-sm font-semibold">{{ partLine }}</p>
                </header>
                <p class="mb-1 text-sm"><span :style="{ fontFamily: MUSIC_FONT }">{{ beatNote }}</span> = {{ song.tempo }}</p>
                <ScoreSystem v-for="(sys, s) in layout.systems" :key="s" :sys="sys" :layout="layout" :song-key="song.key" :name-of="nameOf" class="mb-3" data-print-system :aria-label="t('print.system', { number: s + 1 })" />
                <p v-if="rights" class="mt-4 text-xs">{{ rights }}</p>
            </article>
        </div>`,
    computed: {
        credits() {
            const c = this.song?.credits ?? {};
            return [c.words ? t('print.wordsBy', { name: c.words }) : null, c.tune ? t('print.tuneBy', { name: c.tune }) : null, c.arrangement ? t('print.arrangedBy', { name: c.arrangement.replace(/\s*\(draft\)$/, '') }) : null].filter(Boolean).join(' · ');
        },
        /** One part: "Alto (with the tune above)" or just "Alto". */
        partLine() {
            if (!this.partKeys || this.partKeys.length !== 1) return '';
            const part = this.song.parts.find((p) => p.key === this.partKeys[0]);
            return this.layout.systems[0].staves.some((s) => s.cue) ? t('print.partWithTune', { part: templatePartName(part) }) : templatePartName(part);
        },
        rights() {
            return this.song ? rightsOf(this.song) : null;
        },
        /** The tempo's note: a quarter, or a dotted quarter in 6/8. */
        beatNote() {
            return this.layout.time.beatType === 8 ? '♩.' : '♩';
        },
    },
    async created() {
        this.paper = window.matchMedia('print');
        this.onPrint = () => (this.onPaper = this.paper.matches);
        this.paper.addEventListener('change', this.onPrint);
        const project = await storedProject(this.id);
        if (!project?.template) {
            this.failed = true;
            return;
        }
        const query = new URLSearchParams(location.search);
        this.partKeys = query.get('parts')?.split(',').filter(Boolean) ?? null;
        this.song = asSung(project.template, { transpose: project.transpose || 0, tempo: project.bpm });
        this.title = shownTitle(project);
        this.layout = printLayout(this.song, { partKeys: this.partKeys, cue: query.get('cue') === '1' });
        document.title = this.title;
        // The browser's print at once (Print, or save as a PDF), once the music font is in.
        if (query.get('auto') !== '0') {
            await this.$nextTick();
            await document.fonts?.ready;
            this.print();
        }
    },
    unmounted() {
        this.paper?.removeEventListener('change', this.onPrint);
    },
    methods: {
        goBack,
        print() {
            window.print();
        },
        nameOf(st, first) {
            if (st.cue) return t('print.tune');
            const part = this.song.parts.find((p) => p.key === st.key);
            const name = templatePartName(part);
            return first ? name : `${[...name][0]}.`;
        },
    },
};
