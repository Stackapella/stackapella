// Today's challenge sung (docs/daily-challenge-plan.md, slice 2): under a scored take started from it, one line saying
// so and Share, in both skins. The card is lessons' own (js/components/share-result.js, js/result-card.js), made on the
// device: the day, the song and part, the percent and how each line went; its link is that part's public page, where a
// friend can hear it and start singing the same part. Nothing goes to us but the anonymous count (result_shared).

import { sk } from '../skin.js';
import { dailyFor } from '../daily-pick.js';
import { stars } from '../scoring.js';
import { levelWord } from '../results.js';
import { ShareResult } from './share-result.js';

export const DailyShare = {
    components: { ShareResult },
    // score: the take's (js/scoring.js scoreTake); bleed: the other parts heard, so it didn't count.
    props: { templateId: { type: String, required: true }, partKey: { type: String, required: true }, title: { type: String, required: true }, partName: { type: String, required: true }, score: { type: Object, default: null }, bleed: Boolean, color: { type: String, required: true } },
    setup: () => ({ sk }),
    template: `
        <div v-if="day" class="flex flex-wrap items-center gap-x-4 gap-y-2" data-daily-share>
            <p class="text-sm font-semibold text-cream">{{ sk('home.today.dailySung', 'game.daily.sung', { percent: Math.round(score.percent) }) }}</p>
            <ShareResult :label="t('results.share.result')" :result="result" :daily="daily" :color="color" />
        </div>`,
    computed: {
        /** The challenge's date, when this take was started from today's challenge and scored. */
        day() {
            return this.score?.status === 'scored' && !this.bleed ? dailyFor(this.templateId, this.partKey) : null;
        },
        result() {
            const earned = stars(this.score).stars;
            return { percent: this.score.percent, word: levelWord(earned, this.score.percent), stars: earned, phrases: this.score.phrases ?? [], target: [], dots: [] };
        },
        daily() {
            return { date: this.day, templateId: this.templateId, partKey: this.partKey, title: this.title, part: this.partName };
        },
    },
};
