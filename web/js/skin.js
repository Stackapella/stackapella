// The two skins in code (docs/skins-plan.md): `game()` (from the appearance store), `sk()` for the
// few words that differ ("Course map" or "Stage select"), and <GameOnly> for what only game mode
// shows (XP, badges, combos, boards). Features, flows and data are the same in both: only the look
// and the rewards differ, and what's earned is kept in either.

import { t } from './i18n.js';
import { game } from './stores/appearance.js';

export { game };

/** The serious word, or in game mode the game one: sk('course.map', 'course.mapGame'). */
export function sk(seriousKey, gameKey, values = {}) {
    return t(game() ? gameKey : seriousKey, values);
}

/** Its slot only in game mode. */
export const GameOnly = {
    setup: () => ({ game }),
    template: `<template v-if="game()"><slot /></template>`,
};
