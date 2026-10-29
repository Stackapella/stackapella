// When were you born? (Hillel's 104.png; contract: auth.register and account.update take
// { birthMonth, birthYear }, once). Month and year only, never shown to anyone. Two places ask:
// - after a first Google or Apple sign-in (an account with no birth date yet):
//   it must be answered, and under 13 the account just made goes (the app carries on without one);
// - an 18+ feature refused with 403 adult_required to someone who never gave it: askBirth() opens
//   it, saves it, and says whether they're 18 (the caller retries). Sign-up has the same fields.

import { reactive } from 'vue';
import { api, ApiError } from '../api.js';
import { locale, t } from '../i18n.js';
import { navigate } from '../router.js';
import { account, signOut } from '../stores/account.js';
import { Icon } from './icon.js';
import { Modal, toast } from './ui.js';

/** Month and year selects, as sign-up has them. */
export const BirthFields = {
    props: { month: { type: Number, default: null }, year: { type: Number, default: null }, invalid: Boolean },
    emits: ['update:month', 'update:year'],
    template: `
        <div class="grid grid-cols-[3fr_2fr] gap-2.5">
            <select :value="month ?? ''" required autocomplete="bday-month" translate="no" class="input" :aria-label="t('auth.born.month')" :aria-invalid="invalid ? 'true' : null" @change="$emit('update:month', Number($event.target.value))">
                <option value="" disabled>{{ t('auth.born.month') }}</option>
                <option v-for="(name, i) in months" :key="i" :value="i + 1">{{ name }}</option>
            </select>
            <select :value="year ?? ''" required autocomplete="bday-year" class="input" :aria-label="t('auth.born.year')" :aria-invalid="invalid ? 'true' : null" @change="$emit('update:year', Number($event.target.value))">
                <option value="" disabled>{{ t('auth.born.year') }}</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
        </div>`,
    computed: {
        months() {
            const format = new Intl.DateTimeFormat(locale.lang, { month: 'long' });
            return Array.from({ length: 12 }, (_, i) => format.format(new Date(2026, i, 15)));
        },
        /** This year back a hundred and ten. */
        years() {
            const now = new Date().getFullYear();
            return Array.from({ length: 111 }, (_, i) => now - i);
        },
    },
};

let groups = 0;

/**
 * The birth date as one labelled group (the forms system): the question, month and year in a 3 : 2
 * grid, then the line saying why, or in its place one message for both (red only once an answer
 * fails, never at rest).
 */
export const BirthDate = {
    components: { BirthFields, Icon },
    props: { month: { type: Number, default: null }, year: { type: Number, default: null }, hint: { type: String, default: '' }, error: { type: String, default: '' } },
    emits: ['update:month', 'update:year'],
    data: () => ({ id: `born-${++groups}` }),
    template: `
        <fieldset class="space-y-1.5" :aria-describedby="error ? id + '-error' : hint ? id + '-hint' : null">
            <legend class="label mb-1.5">{{ t('auth.born.title') }}</legend>
            <BirthFields :month="month" :year="year" :invalid="!!error" @update:month="$emit('update:month', $event)" @update:year="$emit('update:year', $event)" />
            <p v-if="error" :id="id + '-error'" class="field-error" role="alert"><Icon name="alert" :size="16" class="mt-px shrink-0" /><span>{{ error }}</span></p>
            <p v-else-if="hint" :id="id + '-hint'" class="help">{{ hint }}</p>
        </fieldset>`,
};

const asked = reactive({ open: false, resolve: null });

/**
 * For an 18+ feature refused (403 adult_required) to someone with no birth date: asks, saves, and
 * resolves true when they're 18 or over (retry), false when not or closed.
 */
export function askBirth() {
    asked.resolve?.(false);
    return new Promise((resolve) => Object.assign(asked, { open: true, resolve }));
}

/** The one dialog, for both: a first provider sign-in, and the 18+ ask. */
export const BirthHost = {
    components: { BirthFields, Icon, Modal },
    data: () => ({ month: null, year: null, busy: false, under: false, notAdult: false, error: null }),
    setup: () => ({ account, asked }),
    template: `
        <Modal v-if="first || asked.open" :title="t('auth.born.title')" :dismissable="!first && !busy" @close="close(false)">
            <form class="space-y-5" data-birth @submit.prevent="save">
                <template v-if="under">
                    <p class="text-cream">{{ t('auth.ageCheck.under') }}</p>
                    <div class="flex justify-end"><button type="button" class="btn-primary" :disabled="busy" @click="remove">{{ t('auth.ageCheck.ok') }}</button></div>
                </template>
                <template v-else-if="notAdult">
                    <p class="text-cream">{{ t('auth.born.notAdult') }}</p>
                    <div class="flex justify-end"><button type="button" class="btn-primary" @click="close(false)">{{ t('auth.ageCheck.ok') }}</button></div>
                </template>
                <template v-else>
                    <p class="text-sm text-muted">{{ first ? t('auth.born.firstWhy') : t('auth.born.adultWhy') }}</p>
                    <BirthFields v-model:month="month" v-model:year="year" :invalid="!!error" />
                    <p v-if="error" class="field-error" role="alert"><Icon name="alert" :size="16" class="mt-px shrink-0" /><span>{{ error }}</span></p>
                    <div class="flex justify-end border-t border-line pt-4"><button type="submit" class="btn-primary" :disabled="busy || !month || !year">{{ t('auth.continue') }}</button></div>
                </template>
            </form>
        </Modal>`,
    computed: {
        /** Signed in with no birth date yet (a first Google or Apple sign-in): the only age question. */
        first: () => !!account.me && !account.me.birthGiven,
    },
    methods: {
        async save() {
            this.busy = true;
            this.error = null;
            try {
                account.me = (await api.account.update({ birthMonth: this.month, birthYear: this.year })).data;
                if (asked.open && !account.me.isAdult) this.notAdult = true;
                else this.close(true);
            } catch (error) {
                if (error instanceof ApiError && error.code === 'too_young') this.under = true;
                else {
                    // Said in the console (its status and code), so a failed save can be told apart (Hillel's sign-up).
                    console.error('The birth date was not saved', error?.status, error?.code, error);
                    this.error = error?.code === 'offline' ? t('common.offline') : t('settings.failed');
                }
            } finally {
                this.busy = false;
            }
        },
        close(ok) {
            const resolve = asked.resolve;
            Object.assign(asked, { open: false, resolve: null });
            this.notAdult = false;
            resolve?.(ok);
        },
        /** Under 13: the account just made goes, and the app works on without one. */
        async remove() {
            this.busy = true;
            try {
                await api.account.delete({});
            } catch (error) {
                // Not deleted (too long since signing in, or offline): signed out here, and told so.
                console.error('Deleting an under-13 account failed', error);
                await signOut().catch((failed) => console.error('Signing out failed', failed));
                toast(t('auth.ageCheck.notDeleted'), { tone: 'error', seconds: 15 });
            } finally {
                account.me = null;
                this.busy = false;
                this.under = false;
                this.close(false);
                navigate('/');
            }
        },
    },
};
