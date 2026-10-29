// Settings' Game mode section, under the Game mode switch (docs/game-elements-plan.md, section 3):
// a plain switch for each arcade moment, shown only while game mode is on. Calm in both skins, like
// every form (the forms system's Switch rows, as Settings' Sounds).

import { tk } from '../i18n.js';
import { game } from '../stores/appearance.js';
import { Switch } from '../components/controls.js';
import { effects } from './effects.js';

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
];

export const GameElements = {
    components: { Switch },
    setup: () => ({ game, effects, ROWS }),
    template: `
        <div v-if="game()" class="space-y-2" data-game-elements>
            <p class="px-1 text-sm text-muted">{{ t('game.settings.intro') }}</p>
            <div class="card divide-y divide-line overflow-hidden">
                <div v-for="row in ROWS" :key="row.name" class="py-3 pe-3 ps-5" :data-game-effect="row.name">
                    <Switch :on="effects[row.name]" :label="t(row.label)" :hint="t(row.hint)" @change="(on) => (effects[row.name] = on)" />
                </div>
            </div>
        </div>`,
};
