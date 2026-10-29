// The CRT boot (docs/game-elements-plan.md; the proposal's idea 20, the Game mode toggle's power-on):
// once a session, the first Sing lane powers on like an old screen, 0.4 s, as it opens and before any
// take. Title cards and READY? switch; reduced motion: never.

import { session } from '../storage.js';
import { effect, still } from './effects.js';

const KEY = 'stackapella.crtShown';

/** Whether this lane boots like a CRT: the first in this session, in game mode with the switch on. */
export function crtOnce() {
    if (!effect('titles') || still() || session.get(KEY)) return false;
    session.set(KEY, '1');
    return true;
}
