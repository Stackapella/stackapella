// Initials on the boards (docs/game-elements-plan.md; docs/game-server-spec.md, section 2; after NBA
// Jam's three letters): arcade initials instead of your profile name, for those who choose them,
// entered with a letter picker on NEW RECORD or in Settings. The server keeps them, refuses offensive
// combinations (its own list; the app shows its message) and shows initials only, never a name, for
// anyone under 18 ("???" until they choose some).

import { api } from '../api.js';
import { t } from '../i18n.js';
import { account } from '../stores/account.js';
import { Modal } from '../components/ui.js';
import { cue } from './chip.js';

export const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/** How a board names an entry: its name, else its initials, else "???". */
export const boardName = (entry) => entry?.name || entry?.initials || '???';

/** Whether an entry is shown by its initials (set in HUD type). */
export const byInitials = (entry) => !entry?.name;

/** A letter `step` places on from `letter`, round the alphabet. */
export function stepLetter(letter, step) {
    const at = LETTERS.indexOf(letter);
    return LETTERS[(((at < 0 ? 0 : at) + step) % 26 + 26) % 26];
}

/** Three letters A to Z from whatever was typed, or null. */
export function cleanInitials(text) {
    const letters = String(text ?? '').toUpperCase().replace(/[^A-Z]/g, '');
    return letters.length === 3 ? letters : null;
}

/** Saves initials (and, by default, shows them on the boards); answers the server's message on a refusal. */
export async function saveInitials(initials, { show = true } = {}) {
    try {
        account.me = (await api.account.update({ initials, ...(show ? { boardsName: 'initials' } : {}) })).data;
        return null;
    } catch (error) {
        return error?.errors?.initials?.[0] ?? error?.errors?.boardsName?.[0] ?? t('game.initials.failed');
    }
}

/** The arcade letter picker, in a sheet: three letters, ▲ and ▼ for each, typing works too; Enter saves. */
export const InitialsPicker = {
    components: { Modal },
    emits: ['close', 'saved'],
    data: () => ({ letters: (account.me?.initials || 'AAA').split(''), at: 0, saving: false, refusal: '' }),
    template: `
        <Modal :title="t('game.initials.title')" @close="$emit('close')">
            <form class="space-y-4" data-game-initials @submit.prevent="save">
                <p class="text-sm text-muted">{{ t('game.initials.hint') }}</p>
                <div class="ge-initials" role="group" :aria-label="t('game.initials.title')">
                    <div v-for="(letter, i) in letters" :key="i" class="ge-initial" :class="i === at ? 'is-now' : ''">
                        <button type="button" class="ge-initial-step" :aria-label="t('game.initials.up', { number: i + 1 })" @click="step(i, -1)">▲</button>
                        <input ref="slot" :value="letter" class="ge-initial-letter" maxlength="1" autocapitalize="characters" autocomplete="off" spellcheck="false" inputmode="text"
                            :aria-label="t('game.initials.letter', { number: i + 1 })" @focus="at = i" @input="typed(i, $event)" @keydown="key(i, $event)" />
                        <button type="button" class="ge-initial-step" :aria-label="t('game.initials.down', { number: i + 1 })" @click="step(i, 1)">▼</button>
                    </div>
                </div>
                <p v-if="refusal" class="text-sm text-bad" role="alert">{{ refusal }}</p>
                <button type="submit" class="btn-primary w-full" :disabled="saving" data-initials-save>{{ t('game.initials.save') }}</button>
            </form>
        </Modal>`,
    mounted() {
        this.$nextTick(() => this.$refs.slot?.[0]?.focus());
    },
    methods: {
        step(i, by) {
            this.letters[i] = stepLetter(this.letters[i], by);
            this.at = i;
            this.refusal = '';
            cue('cursor');
        },
        typed(i, event) {
            const letter = cleanInitials(`${event.target.value}AA`)?.[0];
            if (letter) this.letters[i] = letter;
            event.target.value = this.letters[i];
            this.refusal = '';
            if (letter && i < 2) this.$refs.slot[i + 1].focus();
        },
        key(i, event) {
            const moves = { ArrowUp: -1, ArrowDown: 1 };
            if (moves[event.key]) {
                event.preventDefault();
                this.step(i, moves[event.key]);
            } else if (event.key === 'ArrowRight' && i < 2) this.$refs.slot[i + 1].focus();
            else if (event.key === 'ArrowLeft' && i > 0) this.$refs.slot[i - 1].focus();
            else if (event.key === 'Backspace' && i > 0 && !event.target.value) this.$refs.slot[i - 1].focus();
        },
        async save() {
            this.saving = true;
            this.refusal = (await saveInitials(this.letters.join(''))) ?? '';
            this.saving = false;
            if (this.refusal) return;
            cue('select');
            this.$emit('saved', account.me.initials);
            this.$emit('close');
        },
    },
};
