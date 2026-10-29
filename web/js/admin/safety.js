// The trust and safety screen (docs/trust-and-safety-plan.md, "Admin controls"): the switches for an
// attack, the limits with live overrides, the blocklist, and what happened (security events).
// Every change asks for a reason, which the audit log keeps. Changes need a recent second-factor
// check ("in sudo"): the screen says so before, and sends the person to confirm when the server asks.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { EmptyState, Field, StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';

const FLAGS = {
    shield_mode: tk('admin.flag.shield_mode'),
    pause_signups: tk('admin.flag.pause_signups'),
    pause_sharing: tk('admin.flag.pause_sharing'),
    pause_videos: tk('admin.flag.pause_videos'),
    pause_uploads: tk('admin.flag.pause_uploads'),
    pause_comments: tk('admin.flag.pause_comments'),
    pause_follows: tk('admin.flag.pause_follows'),
    pause_requests: tk('admin.flag.pause_requests'),
    pause_messages: tk('admin.flag.pause_messages'),
    read_only: tk('admin.flag.read_only'),
    gallery: tk('admin.flag.gallery'),
    coaching: tk('admin.flag.coaching'),
    event_schedule: tk('admin.flag.event_schedule'),
};
const FLAG_HINTS = {
    shield_mode: tk('admin.flagHint.shield_mode'),
    pause_signups: tk('admin.flagHint.pause_signups'),
    pause_sharing: tk('admin.flagHint.pause_sharing'),
    pause_videos: tk('admin.flagHint.pause_videos'),
    pause_uploads: tk('admin.flagHint.pause_uploads'),
    pause_comments: tk('admin.flagHint.pause_comments'),
    pause_follows: tk('admin.flagHint.pause_follows'),
    pause_requests: tk('admin.flagHint.pause_requests'),
    pause_messages: tk('admin.flagHint.pause_messages'),
    read_only: tk('admin.flagHint.read_only'),
    gallery: tk('admin.flagHint.gallery'),
    coaching: tk('admin.flagHint.coaching'),
    event_schedule: tk('admin.flagHint.event_schedule'),
};
const BLOCK_HELP = { email: tk('admin.blockHelp.email'), email_domain: tk('admin.blockHelp.email_domain'), ip: tk('admin.blockHelp.ip'), ip_range: tk('admin.blockHelp.ip_range'), card_fingerprint: tk('admin.blockHelp.card_fingerprint') };
const FLAG_VALUES = { off: tk('admin.flagValue.off'), on: tk('admin.flagValue.on'), password: tk('admin.flagValue.password'), all: tk('admin.flagValue.all') };
const BLOCK_TYPES = { email: tk('admin.blockType.email'), email_domain: tk('admin.blockType.email_domain'), ip: tk('admin.blockType.ip'), ip_range: tk('admin.blockType.ip_range'), card_fingerprint: tk('admin.blockType.card_fingerprint') };
export const EVENTS = {
    admin_sign_in: tk('admin.event.admin_sign_in'),
    sign_in_refused: tk('admin.event.sign_in_refused'),
    second_factor_failed: tk('admin.event.second_factor_failed'),
    second_factor_locked: tk('admin.event.second_factor_locked'),
    rate_limited: tk('admin.event.rate_limited'),
    blocked: tk('admin.event.blocked'),
};
const PER = { minute: tk('admin.limit.perMinute'), hour: tk('admin.limit.perHour'), day: tk('admin.limit.perDay') };
const BY = { account: tk('admin.limit.byAccount'), ip: tk('admin.limit.byIp'), email: tk('admin.limit.byEmail'), ip_and_email: tk('admin.limit.byIpAndEmail') };
const TABS = [
    { name: 'switches', label: tk('admin.safety.tab.switches') },
    { name: 'limits', label: tk('admin.safety.tab.limits') },
    { name: 'blocklist', label: tk('admin.safety.tab.blocklist') },
    { name: 'events', label: tk('admin.safety.tab.events') },
];

const SwitchesTab = {
    components: { ChangeSheet, StatusChip, EmptyState, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ flags: null, failed: false, change: null, busy: false, errors: null }),
    setup: () => ({ FLAGS, FLAG_HINTS, FLAG_VALUES, when }),
    template: `
        <div>
            <p class="mb-4 max-w-prose text-sm text-muted">{{ t('admin.switches.intro') }}</p>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!flags" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!flags.length" icon="shield" :title="t('admin.switches.none')" />
            <ul v-else class="grid gap-4 md:grid-cols-2">
                <li v-for="flag in flags" :key="flag.name" class="card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-cream">{{ nameOf(flag) }}</h3>
                            <p class="mt-0.5 text-sm text-muted">{{ FLAG_HINTS[flag.name] ? t(FLAG_HINTS[flag.name]) : t('admin.switches.unknown') }}</p>
                        </div>
                        <StatusChip :tone="flag.value === flag.values[0] ? 'muted' : flag.kind === 'switch' ? 'warn' : 'good'" :label="valueLabel(flag.value)" />
                    </div>
                    <div class="seg mt-4 flex-wrap" role="group" :aria-label="t('admin.switches.setNamed', { name: nameOf(flag) })">
                        <button v-for="value in flag.values" :key="value" type="button" class="seg-option"
                            :aria-pressed="value === flag.value" @click="value !== flag.value && ask(flag, value)">{{ valueLabel(value) }}</button>
                    </div>
                    <p v-if="flag.changedAt" class="mt-3 text-xs text-muted">{{ t('admin.switches.lastChange', { who: flag.changedBy?.name ?? t('admin.system'), when: when(flag.changedAt) }) }}<template v-if="flag.reason">: <span translate="no">{{ flag.reason }}</span></template></p>
                </li>
            </ul>
            <ChangeSheet v-if="change" :title="t('admin.switches.changeTitle', { name: nameOf(change.flag), value: valueLabel(change.value) })" :me="me" :busy="busy" :errors="errors" @close="change = null" @confirm="save" />
        </div>`,
    created() {
        this.load();
    },
    methods: {
        load() {
            return loadInto(this, 'flags', () => api.admin.flags.list());
        },
        nameOf(flag) {
            return FLAGS[flag.name] ? t(FLAGS[flag.name]) : flag.name;
        },
        valueLabel(value) {
            return FLAG_VALUES[value] ? t(FLAG_VALUES[value]) : value;
        },
        ask(flag, value) {
            this.errors = null;
            this.change = { flag, value };
        },
        async save(reason) {
            const { flag, value } = this.change;
            this.busy = true;
            try {
                Object.assign(flag, (await api.admin.flags.update(flag.name, { value, reason })).data);
                toast(t('admin.saved'));
                this.change = null;
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

const LimitsTab = {
    components: { ChangeSheet, Field, StatusChip, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ limits: null, failed: false, change: null, max: null, busy: false, errors: null }),
    setup: () => ({ PER, BY, when }),
    template: `
        <div>
            <p class="mb-4 max-w-prose text-sm text-muted">{{ t('admin.limits.intro') }}</p>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!limits" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <div v-else class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ t('admin.limits.limiter') }}</th><th>{{ t('admin.limits.max') }}</th><th>{{ t('admin.limits.by') }}</th><th>{{ t('admin.limits.default') }}</th><th><span class="sr-only">{{ t('admin.limits.change') }}</span></th></tr></thead>
                    <tbody>
                        <tr v-for="limit in limits" :key="limit.limiter + ':' + limit.rule">
                            <td><code class="tabular-nums text-xs text-cream" translate="no">{{ limit.limiter }} · {{ limit.rule }}</code></td>
                            <td class="whitespace-nowrap text-cream">{{ t('admin.limits.perLabel', { max: limit.max, per: PER[limit.per] ? t(PER[limit.per]) : limit.per }) }}
                                <StatusChip v-if="limit.override !== null" tone="warn" :label="t('admin.limits.overridden')" class="ms-2" />
                                <p v-if="limit.override !== null && limit.changedAt" class="mt-1 text-xs text-muted">{{ t('admin.switches.lastChange', { who: limit.changedBy?.name ?? t('admin.system'), when: when(limit.changedAt) }) }}<template v-if="limit.reason">: <span translate="no">{{ limit.reason }}</span></template></p>
                            </td>
                            <td class="text-muted">{{ BY[limit.by] ? t(BY[limit.by]) : limit.by }}</td>
                            <td class="text-muted">{{ limit.default }}</td>
                            <td class="text-end"><button type="button" class="btn-secondary btn-sm" :aria-label="t('admin.limits.changeNamed', { name: limit.limiter + ' ' + limit.rule })" @click="ask(limit)">{{ t('admin.limits.change') }}</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ChangeSheet v-if="change" :title="t('admin.limits.changeTitle', { name: change.limiter + ' · ' + change.rule })" :me="me" :busy="busy" :errors="errors" :fields="['max']" @close="change = null" @confirm="save">
                <Field :label="t('admin.limits.newMax', { per: PER[change.per] ? t(PER[change.per]) : change.per })" :help="t('admin.limits.newMaxHelp', { default: change.default })" :error="errors?.max" v-slot="f">
                    <input :id="f.id" v-model.number="max" type="number" min="1" max="100000" inputmode="numeric" class="input" :placeholder="String(change.default)" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
            </ChangeSheet>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        load() {
            return loadInto(this, 'limits', () => api.admin.limits.list());
        },
        ask(limit) {
            this.errors = null;
            this.max = limit.override;
            this.change = limit;
        },
        async save(reason) {
            const limit = this.change;
            this.busy = true;
            try {
                const max = Number.isInteger(this.max) ? this.max : null;
                Object.assign(limit, (await api.admin.limits.update(limit.limiter, limit.rule, { max, reason })).data);
                toast(t('admin.saved'));
                this.change = null;
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
    },
};

const BlocklistTab = {
    components: { ChangeSheet, Field, EmptyState, StatusChip, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ blocks: null, failed: false, cursor: null, loadingMore: false, type: '', adding: false, form: { type: 'email', value: '', expiresAt: '' }, lifting: null, busy: false, errors: null }),
    setup: () => ({ BLOCK_TYPES, BLOCK_HELP, when }),
    template: `
        <div>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <p class="max-w-prose text-sm text-muted">{{ t('admin.blocklist.intro') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="sr-only" for="block-filter">{{ t('admin.blocklist.filter') }}</label>
                    <select id="block-filter" v-model="type" class="input !w-auto !py-2" @change="load()">
                        <option value="">{{ t('admin.blocklist.allTypes') }}</option>
                        <option v-for="(label, key) in BLOCK_TYPES" :key="key" :value="key">{{ t(label) }}</option>
                    </select>
                    <button type="button" class="btn-primary btn-sm" @click="adding = true; errors = null">{{ t('admin.blocklist.add') }}</button>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!blocks" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!blocks.length" icon="shield" :title="t('admin.blocklist.none')" :body="t('admin.blocklist.noneHint')" />
            <div v-else class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ t('admin.blocklist.what') }}</th><th>{{ t('admin.reason') }}</th><th>{{ t('admin.blocklist.until') }}</th><th>{{ t('admin.blocklist.added') }}</th><th><span class="sr-only">{{ t('admin.blocklist.lift') }}</span></th></tr></thead>
                    <tbody>
                        <tr v-for="block in blocks" :key="block.id">
                            <td><span class="block text-xs text-muted">{{ BLOCK_TYPES[block.type] ? t(BLOCK_TYPES[block.type]) : block.type }}</span><code class="tabular-nums text-xs text-cream break-all" translate="no">{{ block.value }}</code></td>
                            <td class="text-muted" translate="no">{{ block.reason }}</td>
                            <td class="whitespace-nowrap"><StatusChip v-if="block.expiresAt && new Date(block.expiresAt) < new Date()" tone="muted" :label="t('admin.blocklist.expired')" /><time v-else-if="block.expiresAt" class="text-muted" :datetime="block.expiresAt">{{ when(block.expiresAt) }}</time><span v-else class="text-muted">{{ t('admin.blocklist.forever') }}</span></td>
                            <td class="whitespace-nowrap text-xs text-muted"><time :datetime="block.createdAt">{{ when(block.createdAt) }}</time><br><span translate="no">{{ block.createdBy?.name }}</span></td>
                            <td class="text-end"><button type="button" class="btn-ghost btn-sm" :aria-label="t('admin.blocklist.liftNamed', { value: block.value })" @click="lifting = block; errors = null">{{ t('admin.blocklist.lift') }}</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button v-if="cursor && !failed" type="button" class="btn-secondary mt-4" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
            <ChangeSheet v-if="adding" :title="t('admin.blocklist.addTitle')" :me="me" :busy="busy" :errors="errors" :fields="['type', 'value', 'expiresAt']" @close="adding = false" @confirm="add">
                <Field :label="t('admin.blocklist.type')" :error="errors?.type" required v-slot="f">
                    <select :id="f.id" v-model="form.type" class="input" :aria-invalid="f.invalid">
                        <option v-for="(label, key) in BLOCK_TYPES" :key="key" :value="key">{{ t(label) }}</option>
                    </select>
                </Field>
                <Field :label="t('admin.blocklist.value')" :help="t(BLOCK_HELP[form.type])" :error="errors?.value" required v-slot="f">
                    <input :id="f.id" v-model="form.value" required maxlength="255" class="input" autocomplete="off" spellcheck="false" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
                <Field :label="t('admin.blocklist.expiresAt')" :help="t('admin.blocklist.expiresHelp')" :error="errors?.expiresAt" v-slot="f">
                    <input :id="f.id" v-model="form.expiresAt" type="datetime-local" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                </Field>
            </ChangeSheet>
            <ChangeSheet v-if="lifting" :title="t('admin.blocklist.liftTitle', { value: lifting.value })" :me="me" :busy="busy" :errors="errors" :reasoned="false" @close="lifting = null" @confirm="lift" />
        </div>`,
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'blocks', () => api.admin.blocklist.list({ type: this.type, cursor: more ? this.cursor : null }), { more });
        },
        async add(reason) {
            this.busy = true;
            try {
                const expiresAt = this.form.expiresAt ? new Date(this.form.expiresAt).toISOString().replace(/\.\d+Z$/, 'Z') : null;
                const block = (await api.admin.blocklist.create({ type: this.form.type, value: this.form.value.trim(), reason, expiresAt })).data;
                // Shown at once, unless the list is filtered to another type.
                if (!this.type || this.type === block.type) this.blocks = [block, ...(this.blocks ?? [])];
                this.form = { type: this.form.type, value: '', expiresAt: '' };
                this.adding = false;
                toast(t('admin.saved'));
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.busy = false;
            }
        },
        async lift() {
            const block = this.lifting;
            this.busy = true;
            try {
                await api.admin.blocklist.delete(block.id);
                this.blocks = this.blocks.filter((b) => b.id !== block.id);
                this.lifting = null;
                toast(t('admin.blocklist.lifted'));
            } catch (error) {
                if (error instanceof ApiError && error.code === 'not_found') {
                    this.blocks = this.blocks.filter((b) => b.id !== block.id); // already lifted
                    this.lifting = null;
                } else {
                    refused(error);
                }
            } finally {
                this.busy = false;
            }
        },
    },
};

const EventsTab = {
    components: { EmptyState, LoadFailed },
    props: { me: { type: Object, required: true } },
    data: () => ({ events: null, failed: false, cursor: null, loadingMore: false, type: '' }),
    setup: () => ({ EVENTS, when }),
    template: `
        <div>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <p class="max-w-prose text-sm text-muted">{{ t('admin.events.intro') }}</p>
                <div>
                    <label class="sr-only" for="event-filter">{{ t('admin.events.filter') }}</label>
                    <select id="event-filter" v-model="type" class="input !w-auto !py-2" @change="load()">
                        <option value="">{{ t('admin.events.allTypes') }}</option>
                        <option v-for="(label, key) in EVENTS" :key="key" :value="key">{{ t(label) }}</option>
                    </select>
                </div>
            </div>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!events" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!events.length" icon="shield" :title="t('admin.events.none')" />
            <div v-else class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ t('admin.when') }}</th><th>{{ t('admin.events.what') }}</th><th>{{ t('admin.who') }}</th><th>{{ t('admin.events.where') }}</th><th>{{ t('admin.details') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.id">
                            <td class="whitespace-nowrap text-muted"><time :datetime="event.createdAt">{{ when(event.createdAt) }}</time></td>
                            <td class="text-cream">{{ EVENTS[event.type] ? t(EVENTS[event.type]) : t('admin.events.other') }}<code v-if="!EVENTS[event.type]" class="ms-1 tabular-nums text-xs text-muted" translate="no">{{ event.type }}</code></td>
                            <td class="text-muted" translate="no">{{ event.user?.name ?? '' }}</td>
                            <td><code class="tabular-nums text-xs text-muted" translate="no">{{ event.ip ?? event.ipHash ?? '' }}</code></td>
                            <td><code v-for="(value, key) in event.context ?? {}" :key="key" class="me-2 tabular-nums text-xs text-muted" translate="no">{{ key }}: {{ value }}</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button v-if="cursor && !failed" type="button" class="btn-secondary mt-4" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'events', () => api.admin.securityEvents.list({ type: this.type, cursor: more ? this.cursor : null }), { more });
        },
    },
};

/** The four tabs, as the ARIA tabs pattern: arrow keys move between them, Home and End to the ends. */
export const SafetySection = {
    components: { Icon, SwitchesTab, LimitsTab, BlocklistTab, EventsTab },
    props: { me: { type: Object, required: true } },
    data() {
        const asked = new URLSearchParams(location.search).get('tab');
        return { tab: TABS.some((item) => item.name === asked) ? asked : TABS[0].name };
    },
    setup: () => ({ TABS }),
    template: `
        <div class="space-y-6">
            <h1 id="safety-heading" class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.safety') }}</h1>
            <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line" role="tablist" aria-labelledby="safety-heading" @keydown="move">
                <button v-for="item in TABS" :id="'tab-' + item.name" :key="item.name" type="button" role="tab" :aria-selected="tab === item.name" :aria-controls="'panel-' + item.name" :tabindex="tab === item.name ? 0 : -1"
                    class="-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition" :class="tab === item.name ? 'border-accent text-cream' : 'border-transparent text-muted hover:text-cream'"
                    @click="pick(item.name)">{{ t(item.label) }}</button>
            </div>
            <div :id="'panel-' + tab" role="tabpanel" :aria-labelledby="'tab-' + tab" tabindex="0">
                <SwitchesTab v-if="tab === 'switches'" :me="me" />
                <LimitsTab v-else-if="tab === 'limits'" :me="me" />
                <BlocklistTab v-else-if="tab === 'blocklist'" :me="me" />
                <EventsTab v-else :me="me" />
            </div>
        </div>`,
    methods: {
        pick(name) {
            this.tab = name;
            const url = new URL(location.href);
            url.searchParams.set('tab', name);
            history.replaceState({}, '', url);
        },
        move(event) {
            const index = TABS.findIndex((item) => item.name === this.tab);
            const to = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: TABS.length - 1 }[event.key];
            if (to === undefined) return;
            event.preventDefault();
            const next = TABS[(to + TABS.length) % TABS.length].name;
            this.pick(next);
            this.$nextTick(() => document.getElementById(`tab-${next}`)?.focus());
        },
    },
};
