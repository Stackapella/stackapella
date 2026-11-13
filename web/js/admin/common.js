// What the admin's screens share: dates, refusals, lists that load in pages, and the sheet every
// change goes through (a reason, the sudo note, the server's field messages).

import { reactive } from 'vue';
import { ApiError } from '../api.js';
import { formatDate, formatNumber, t } from '../i18n.js';
import { Field, Notice } from '../components/form.js';
import { Modal, toast } from '../components/ui.js';
import { explain, inSudo, sendToSignIn } from './auth.js';
import { local } from '../storage.js';

/** A phone's width (under Tailwind's sm): where a wide table becomes a card a row. Follows the window. */
const PHONE = window.matchMedia('(max-width: 639px)');
export const screen = reactive({ phone: PHONE.matches });
PHONE.addEventListener('change', (event) => (screen.phone = event.matches));

/**
 * Admin view choices are remembered (Hillel, 2026-11-02: "remember the today/7 day/ setting when I
 * leave the page and come back"): a range, a tab or a sort, kept on this device and read back
 * before the page's first load. `choices` is { field: [the values offered] }; a kept value no longer
 * offered is ignored. Storage that throws just means nothing is remembered (storage.js). `when`
 * leaves out a use with no picker (the funnel inside Experiments).
 */
export function remembers(page, choices, when = () => true) {
    const key = (field) => `stackapella.admin.${page}.${field}`;
    return {
        created() {
            if (!when(this)) return;
            for (const [field, offered] of Object.entries(choices)) {
                const kept = local.get(key(field));
                const value = offered.find((o) => String(o) === kept);
                if (value !== undefined) this[field] = value;
                this.$watch(field, (now) => local.set(key(field), String(now)));
            }
        },
    };
}

/** "Oct 26, 2026, 2:05 PM" for a time the API sends. */
export function when(time) {
    return time ? formatDate(time, { dateStyle: 'medium', timeStyle: 'short' }) : '';
}

/** The API's 422 field messages (already translated on the server), or null for another refusal. */
export function errorsFrom(error) {
    if (error instanceof ApiError && error.code === 'validation_failed') return error.errors ?? {};
    return null;
}

/** Says what a refusal means; navigates for sign-in and sudo. */
export function refused(error) {
    if (sendToSignIn(error)) return;
    console.error('Admin request failed', error);
    const message = explain(error);
    if (message) toast(message, { tone: 'error', seconds: 10 });
}

/**
 * Loads a list into `vm`, guarding against an older answer arriving last (a filter changed
 * meanwhile) and saying when it failed, rather than showing an empty list.
 */
export async function loadInto(vm, field, request, { more = false } = {}) {
    const ticket = (vm.ticket = (vm.ticket ?? 0) + 1);
    vm.loadingMore = more;
    try {
        const page = await request();
        if (ticket !== vm.ticket) return;
        vm[field] = more ? [...vm[field], ...page.data.filter((item) => !vm[field].some((had) => had.id === item.id))] : page.data;
        vm.cursor = page.meta?.nextCursor ?? null;
        vm.failed = false;
    } catch (error) {
        if (ticket !== vm.ticket) return;
        if (!sendToSignIn(error)) {
            console.error('Admin list failed', error);
            vm.failed = explain(error) ?? t('admin.requestFailed');
        }
    } finally {
        if (ticket === vm.ticket) vm.loadingMore = false;
    }
}

/** A list that couldn't load: says so, with Try again (never an empty list during an incident). */
export const LoadFailed = {
    components: { Notice },
    props: { message: { type: String, required: true } },
    emits: ['retry'],
    template: `<Notice tone="bad">{{ message }} <button type="button" class="link" @click="$emit('retry')">{{ t('common.tryAgain') }}</button></Notice>`,
};

/**
 * The sheet every change goes through: what's changing (the slot), a reason (required, unless
 * `reasoned` is false), the sudo note, and any refusal. It can't be closed while saving.
 */
export const ChangeSheet = {
    components: { Modal, Field, Notice },
    props: {
        title: { type: String, required: true },
        me: { type: Object, required: true },
        busy: Boolean,
        errors: Object, // the 422's field messages
        fields: { type: Array, default: () => [] }, // the fields the slot shows errors for itself
        reasoned: { type: Boolean, default: true },
        needsSudo: { type: Boolean, default: true }, // the server asks for a recent second factor
        told: Boolean, // the reason goes to the person as their notice's note (views/notices.js), not only the audit log
    },
    emits: ['close', 'confirm'],
    data: () => ({ reason: '' }),
    computed: {
        sudo: (vm) => inSudo(vm.me),
        /** 422 messages for fields this sheet has no place for. */
        otherErrors() {
            return Object.entries(this.errors ?? {}).filter(([field]) => field !== 'reason' && !this.fields.includes(field)).flatMap(([, messages]) => messages);
        },
    },
    template: `
        <Modal :title="title" :dismissable="!busy" @close="$emit('close')">
            <form class="space-y-5" @submit.prevent="$emit('confirm', reason.trim())">
                <slot />
                <Field v-if="reasoned" :label="told ? t('admin.reasonToldLabel') : t('admin.reason')" :help="told ? t('admin.reasonTold') : t('admin.reasonHelp')" :error="errors?.reason" required v-slot="f">
                    <textarea :id="f.id" v-model="reason" rows="2" maxlength="500" required class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                </Field>
                <Notice v-if="otherErrors.length" tone="bad">{{ otherErrors.join(' ') }}</Notice>
                <Notice v-else-if="errors && !Object.keys(errors).length" tone="bad">{{ t('admin.requestFailed') }}</Notice>
                <Notice v-if="needsSudo && !sudo" tone="info">{{ t('admin.sudoFirst') }}</Notice>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy || (reasoned && !reason.trim())">{{ busy ? t('common.saving') : t('admin.confirmChange') }}</button>
                </div>
            </form>
        </Modal>`,
};

export const FEW = 20; // under this, a change is said as a number, never a percentage (it'd be noise); the server's lowSample agrees

/** A tile's change against an earlier day: `+11%` from 20 up, `+2` under it (a percentage would be noise), or the same. */
export function change(now, before) {
    if (now === before) return { dir: 'same', text: t('admin.growth.same') };
    const sign = now > before ? '+' : '';
    const text = before >= FEW ? `${sign}${formatNumber(Math.round(((now - before) / before) * 100))}%` : `${sign}${formatNumber(now - before)}`;
    return { dir: now > before ? 'up' : 'down', text };
}

/**
 * The report's time zone (the server's `timezone`, a zone id such as "Europe/London"; never written
 * in the web's code) as people say it: the city, "London", or "UTC". An id the browser doesn't know
 * is said as it is.
 */
export function zoneName(zone) {
    if (!zone || zone === 'UTC' || zone === 'Etc/UTC') return 'UTC';
    try {
        new Intl.DateTimeFormat('en', { timeZone: zone }); // a zone the browser knows
    } catch {
        return zone;
    }
    return zone.split('/').at(-1).replaceAll('_', ' ');
}
