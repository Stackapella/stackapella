// Settings' Game mode section, under the Game mode switch (docs/game-elements-plan.md, section 3):
// a plain switch for each arcade moment, shown only while game mode is on. Calm in both skins, like
// every form (the forms system's Switch rows, as Settings' Sounds).

import { tk } from '../i18n.js';
import { game } from '../stores/appearance.js';
import { Switch } from '../components/controls.js';
import { effects } from './effects.js';
import { api } from '../api.js';
import { account } from '../stores/account.js';
import { InitialsPicker } from './initials.js';

const ROWS = [
    { name: 'sounds', label: tk('game.settings.sounds'), hint: tk('game.settings.soundsHint') },
    { name: 'hud', label: tk('game.settings.hud'), hint: tk('game.settings.hudHint') },
    { name: 'popups', label: tk('game.settings.popups'), hint: tk('game.settings.popupsHint') },
    { name: 'titles', label: tk('game.settings.titles'), hint: tk('game.settings.titlesHint') },
    { name: 'celebrations', label: tk('game.settings.celebrations'), hint: tk('game.settings.celebrationsHint') },
    { name: 'title', label: tk('game.settings.title'), hint: tk('game.settings.titleHint') },
    { name: 'stages', label: tk('game.settings.stages'), hint: tk('game.settings.stagesHint') },
    { name: 'versus', label: tk('game.settings.versus'), hint: tk('game.settings.versusHint') },
    { name: 'select', label: tk('game.settings.select'), hint: tk('game.settings.selectHint') },
    { name: 'ghost', label: tk('game.settings.ghost'), hint: tk('game.settings.ghostHint') },
    { name: 'daily', label: tk('game.settings.daily'), hint: tk('game.settings.dailyHint') },
    { name: 'monthly', label: tk('game.settings.monthly'), hint: tk('game.settings.monthlyHint') },
];

export const GameElements = {
    components: { Switch, InitialsPicker },
    setup: () => ({ game, effects, ROWS, account }),
    data: () => ({ naming: false, saving: false }),
    template: `
        <div v-if="game()" class="space-y-2" data-game-elements>
            <p class="px-1 text-sm text-muted">{{ t('game.settings.intro') }}</p>
            <div class="card divide-y divide-line overflow-hidden">
                <div v-for="row in ROWS" :key="row.name" class="py-3 pe-3 ps-5" :data-game-effect="row.name">
                    <Switch :on="effects[row.name]" :label="t(row.label)" :hint="t(row.hint)" @change="(on) => (effects[row.name] = on)" />
                </div>
                <!-- Initials on the boards (docs/game-server-spec.md, section 2): signed in; under 18, always -->
                <template v-if="account.me">
                    <div class="py-3 pe-3 ps-5" data-game-initials-row>
                        <Switch :on="account.me.boardsName === 'initials'" :disabled="!account.me.isAdult || saving" :label="t('game.settings.initials')"
                            :hint="account.me.isAdult ? t('game.settings.initialsHint') : t('game.settings.initialsYoung')" @change="showInitials" />
                    </div>
                    <div class="flex items-center gap-4 py-3 pe-3 ps-5" data-game-initials-yours>
                        <p class="min-w-0 flex-1"><span class="block font-semibold text-cream">{{ t('game.settings.initialsYours') }}</span><span class="block text-[0.8125rem] text-muted" translate="no">{{ account.me.initials || t('game.settings.initialsNone') }}</span></p>
                        <button type="button" class="btn-secondary btn-sm" @click="naming = true">{{ account.me.initials ? t('game.settings.initialsChange') : t('game.settings.initialsChoose') }}</button>
                    </div>
                </template>
            </div>
            <InitialsPicker v-if="naming" @close="naming = false" />
        </div>`,
    methods: {
        /** Initials or your name on the boards; with no initials yet, choosing them comes first. */
        async showInitials(on) {
            if (on && !account.me.initials) {
                this.naming = true;
                return;
            }
            this.saving = true;
            try {
                account.me = (await api.account.update({ boardsName: on ? 'initials' : 'name' })).data;
            } catch (error) {
                console.warn('Not changed', error);
            } finally {
                this.saving = false;
            }
        },
    },
};
