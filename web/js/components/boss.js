// A unit's boss round's screens (docs/boss-round-plan.md): its intro, the bar while it's sung, and its end. With the
// Boss rounds switch on in game mode (effect('boss')) it's a boss stage: the challenge's tilted banner between hazard
// stripes, an abstract gem (never a figure) that cracks as the bar drops, PRESS START, and "Boss beaten!" or
// "Continue?". Otherwise, in either skin, it's a calm Unit review: the same steps and the same honest score, with no
// bar and no fanfare. The bar is the running score itself: each note lit takes its share off, and the 80% line is drawn.

import { formatNumber, t } from '../i18n.js';
import { sk } from '../skin.js';
import { effect } from '../game/effects.js';
import { BOSS_PASS } from '../boss.js';

/** How much of the boss is left (100 to 0) from the round's notes: those already scored and those lit in this take. */
export const bossLeft = ({ total, scored, live }) => (total > 0 ? Math.max(0, Math.round(100 - ((scored + live) / total) * 100)) : 100);

/** The gem's cracks: none, then one at each quarter gone. */
export const crackOf = (left) => (left > 75 ? 0 : left > 50 ? 1 : left > 25 ? 2 : 3);

/** Before the round: what it is, the line to beat, your best, and the way in (or Not now). */
export const BossIntro = {
    props: {
        unit: { type: Number, required: true }, // 1-based
        unitTitle: { type: String, required: true },
        title: { type: String, required: true },
        best: { type: Number, default: null },
        beaten: { type: Boolean, default: false },
        ready: { type: Boolean, default: true }, // its songs' notes are in
    },
    emits: ['start', 'later'],
    setup: () => ({ t, sk, formatNumber, effect, BOSS_PASS }),
    template: `
        <section v-if="effect('boss')" class="ge-vs ge-boss" data-boss-intro data-game-boss aria-labelledby="boss-title">
            <h1 id="boss-title" ref="heading" tabindex="-1" class="sr-only">{{ t('boss.game.heading', { unit, title }) }}</h1>
            <div class="ge-vs-banner" aria-hidden="true"><i class="ge-vs-hazard"></i><p class="ge-vs-band">{{ t('boss.game.banner') }}</p><i class="ge-vs-hazard"></i></div>
            <span class="ge-boss-gem" aria-hidden="true"></span>
            <dl class="ge-vs-card">
                <div><dt>{{ t('boss.game.stage', { unit }) }}</dt><dd class="ge-vs-title" translate="no">{{ title }}</dd><dd class="ge-vs-yours" translate="no">{{ unitTitle }}</dd></div>
                <div class="ge-vs-beat"><dt>{{ t('game.versus.toBeat') }}</dt><dd class="ge-vs-score">{{ formatNumber(BOSS_PASS) }}%</dd><dd class="ge-vs-yours" data-boss-best>{{ best === null ? t('boss.noTry') : beaten ? t('boss.bestBeaten', { percent: formatNumber(best) }) : t('boss.best', { percent: formatNumber(best) }) }}</dd></div>
            </dl>
            <div class="ge-vs-foot">
                <button type="button" class="btn-primary btn-lg w-full" :disabled="!ready" data-boss-start @click="$emit('start')">{{ ready ? t('game.home.pressStart') : t('course.loading') }}</button>
                <a href="/learn" class="ge-vs-later" data-boss-later @click.prevent="$emit('later')">{{ t('game.versus.notNow') }}</a>
            </div>
        </section>
        <section v-else class="flex flex-col gap-5" data-boss-intro data-unit-review aria-labelledby="boss-title">
            <p class="text-sm font-semibold text-accent-strong" translate="no">{{ unitTitle }}</p>
            <h1 id="boss-title" ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('boss.review.title') }}</h1>
            <p class="text-lg text-cream" translate="no">{{ title }}</p>
            <p class="max-w-prose text-muted">{{ sk('boss.review.lineSerious', 'boss.review.line', { percent: formatNumber(BOSS_PASS) }) }}</p>
            <p v-if="best !== null" class="text-sm text-muted" data-boss-best>{{ beaten ? t('boss.review.bestPassed', { percent: formatNumber(best) }) : t('boss.best', { percent: formatNumber(best) }) }}</p>
            <div class="flex flex-wrap gap-3 max-sm:flex-col">
                <button type="button" class="btn-primary" :disabled="!ready" data-boss-start @click="$emit('start')">{{ ready ? t('boss.review.start') : t('course.loading') }}</button>
                <button type="button" class="btn-secondary" data-boss-later @click="$emit('later')">{{ t('game.versus.notNow') }}</button>
            </div>
        </section>`,
    methods: {
        focus() {
            this.$refs.heading?.focus({ preventScroll: true });
        },
    },
};

/** The boss's bar while the round is sung (game mode, the switch on): what's left, the line to get under, the round. */
export const BossBar = {
    props: {
        total: { type: Number, required: true }, // the round's scored notes
        scored: { type: Number, default: 0 }, // notes' worth already scored (percent x notes of finished steps)
        live: { type: Number, default: 0 }, // notes lit in this take
        round: { type: Number, required: true }, // 1-based
        rounds: { type: Number, required: true },
        said: { type: String, default: '' }, // a step's end, for a screen reader
    },
    setup: () => ({ t, formatNumber, BOSS_PASS }),
    computed: {
        left: (vm) => bossLeft(vm),
        crack: (vm) => crackOf(vm.left),
    },
    template: `
        <div class="ge-boss-hud" data-boss-bar :data-left="left">
            <span class="ge-boss-gem is-small" :data-crack="crack" aria-hidden="true"></span>
            <div class="min-w-0 flex-1">
                <p class="ge-boss-label" aria-hidden="true"><span>{{ t('boss.game.bar') }}</span><span>{{ t('boss.game.round', { round, rounds }) }}</span></p>
                <span class="ge-boss-track" aria-hidden="true"><span class="ge-boss-fill" :style="{ transform: 'scaleX(' + left / 100 + ')' }"></span><span class="ge-boss-line" :style="{ insetInlineStart: (100 - BOSS_PASS) + '%' }"></span></span>
            </div>
            <p class="sr-only" role="status">{{ said }}</p>
        </div>`,
};

/** The round's end: beaten, not yet (Continue?), or not counted (a step not heard or skipped). */
export const BossEnd = {
    props: {
        done: { type: Object, required: true }, // { counted, percent, beaten, first, newBest, gained, skipped }
        title: { type: String, required: true },
    },
    emits: ['again', 'back'],
    setup: () => ({ t, formatNumber, effect, BOSS_PASS, crackOf }),
    template: `
        <section v-if="effect('boss')" class="ge-vs ge-boss is-done" data-boss-end :data-beaten="done.beaten ? '' : null" aria-labelledby="boss-end-title">
            <div class="ge-vs-banner" :class="done.beaten ? 'is-won' : ''"><i class="ge-vs-hazard" aria-hidden="true"></i><h1 id="boss-end-title" ref="heading" tabindex="-1" class="ge-vs-band outline-none">{{ !done.counted ? t('boss.notCounted.title') : done.beaten ? t('boss.game.beaten') : t('game.done.continue') }}</h1><i class="ge-vs-hazard" aria-hidden="true"></i></div>
            <span class="ge-boss-gem" :data-crack="done.beaten ? 4 : done.counted ? crackOf(100 - done.percent) : 0" aria-hidden="true"></span>
            <dl v-if="done.counted" class="ge-vs-card">
                <div><dt>{{ t('boss.yourScore') }}</dt><dd class="ge-vs-score" data-boss-percent>{{ formatNumber(done.percent) }}%</dd><dd v-if="done.newBest" class="ge-vs-yours">{{ t('boss.newBest') }}</dd></div>
                <div class="ge-vs-beat"><dt>{{ t('game.versus.toBeat') }}</dt><dd class="ge-vs-score">{{ formatNumber(BOSS_PASS) }}%</dd><dd v-if="done.gained" class="ge-vs-yours" data-boss-xp>{{ t('boss.xp', { xp: formatNumber(done.gained) }) }}</dd></div>
            </dl>
            <p class="text-center text-cream" data-boss-line>{{ line }}</p>
            <div class="ge-vs-foot">
                <button type="button" class="btn-primary btn-lg w-full" data-boss-again-or-back @click="$emit(done.beaten ? 'back' : 'again')">{{ done.beaten ? t('boss.backToMap') : t('boss.tryAgain') }}</button>
                <button type="button" class="ge-vs-later" data-boss-other @click="$emit(done.beaten ? 'again' : 'back')">{{ done.beaten ? t('boss.playAgain') : t('game.versus.notNow') }}</button>
            </div>
        </section>
        <section v-else class="flex flex-col items-center gap-5 text-center" data-boss-end data-unit-review :data-beaten="done.beaten ? '' : null" aria-labelledby="boss-end-title">
            <h1 id="boss-end-title" ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ !done.counted ? t('boss.notCounted.title') : done.beaten ? t('boss.review.passed', { percent: formatNumber(done.percent) }) : t('boss.review.notYet', { percent: formatNumber(done.percent) }) }}</h1>
            <p class="max-w-prose text-muted" data-boss-line>{{ line }}</p>
            <div class="flex flex-wrap justify-center gap-3 max-sm:w-full max-sm:flex-col">
                <button type="button" class="btn-primary" data-boss-again-or-back @click="$emit(done.beaten ? 'back' : 'again')">{{ done.beaten ? t('boss.backToMap') : t('boss.tryAgain') }}</button>
                <button type="button" class="btn-secondary" data-boss-other @click="$emit(done.beaten ? 'again' : 'back')">{{ done.beaten ? t('boss.playAgain') : t('course.backToCourse') }}</button>
            </div>
        </section>`,
    computed: {
        /** The honest line under it: what happened, and what beats it. */
        line() {
            const d = this.done;
            if (!d.counted) return d.skipped ? t('boss.notCounted.skipped') : t('boss.notCounted.unheard');
            if (d.beaten) return effect('boss') ? t('boss.game.beatenLine', { title: this.title }) : t('boss.review.passedLine');
            const values = { percent: formatNumber(d.percent), pass: formatNumber(BOSS_PASS) };
            return effect('boss') ? t('boss.under', values) : t('boss.review.under', values);
        },
    },
    methods: {
        focus() {
            this.$refs.heading?.focus({ preventScroll: true });
        },
    },
};
