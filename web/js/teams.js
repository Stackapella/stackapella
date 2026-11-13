// Teaming up is Plus (docs/pricing-plan.md, top) only while the plus_teams switch is on: auth.config's plusTeams. Until
// then singing together is free as before, so no Plus moment is offered for it, nothing is said to be paused and the Plus
// page doesn't sell it. One reactive flag for every screen that asks (the groups, the Plus page, the song's invites).

import { reactive, watch } from 'vue';
import { account } from './stores/account.js';

export const teams = reactive({ on: false });

// Mirrors auth.config as main.js loads it at start (and as it's read again later): no request of its own, so a module
// that only asks (plan.js's lockReason) never fetches auth.config just by being imported.
watch(
    () => account.config,
    (config) => {
        if (config) teams.on = config.plusTeams === true;
    },
    { immediate: true, flush: 'sync' }, // sync: set before anything awaiting the config reads it
);
