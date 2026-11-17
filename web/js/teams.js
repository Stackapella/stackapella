// Teaming up is Plus (docs/pricing-plan.md, top) only while the plus_teams switch is on: auth.config's plusTeams. Until
// then singing together is free as before, so no Plus moment is offered for it, nothing is said to be paused and the Plus
// page doesn't sell it. One reactive flag for every screen that asks (the groups, the Plus page, the song's invites).

import { reactive, watch } from 'vue';
import { account } from './stores/account.js';

// `private`: shared private arrangements need everyone's own Plus (the plus_private switch, auth.config plusPrivate;
// Hillel, 2026-11-17). Off, nothing here asks for Plus on opening a song.
export const teams = reactive({ on: false, private: false });

// Mirrors auth.config as main.js loads it at start (and as it's read again later): no request of its own, so a module
// that only asks (plan.js's lockReason) never fetches auth.config just by being imported.
watch(
    () => account.config,
    (config) => {
        if (config) {
            teams.on = config.plusTeams === true;
            teams.private = config.plusPrivate === true;
        }
    },
    { immediate: true, flush: 'sync' }, // sync: set before anything awaiting the config reads it
);
