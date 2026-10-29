// Home as the arcade's title screen (docs/game-elements-plan.md; the proposal's board 08), as a reskin
// of what Home already shows (Hillel: "I'd prefer we reskinned these elements to be more game like"):
// the course card is a stage card, its progress a power bar and Continue lesson the Press start
// button (home.js and the ge- rules), the week's day bars are marquee bulbs, and you are P1 at your
// level in the avatar chip (this file). Every number is the course's own.

import { formatNumber } from '../i18n.js';
import { levelFor } from '../course.js';
import { learning } from '../stores/learning.js';

/** "P1 · LV 2" inside the avatar chip: you, and your level from your XP. */
export const PlayerChip = {
    setup: () => ({ formatNumber }),
    template: `<span class="ge-p1" aria-hidden="true" data-game-player><span class="ge-p1-tag">{{ t('game.home.p1') }}</span><span>{{ t('game.home.level', { level: formatNumber(level) }) }}</span></span>`,
    computed: {
        level: () => levelFor(learning.progress.xp.total).level,
    },
};
