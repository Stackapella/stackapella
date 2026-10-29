// Form and admin building blocks, after iaw's (resources/views/components/form/field.blade.php and
// components/admin/status.blade.php), in Stackapella's look. The styleguide (/styleguide) shows
// them all; every form and admin screen is built from them.

import { Icon } from './icon.js';

let fields = 0;

/**
 * A labelled field: the label (with "(optional)" unless required, and a counter like 26/300 at the
 * end of its row), the input in the slot, then the help, or in its place the error with an icon. The slot gets `id`, `describedby` and `invalid` to put on its
 * input: <Field v-slot="f"><input :id="f.id" :aria-describedby="f.describedby" :aria-invalid="f.invalid" class="input"></Field>
 */
export const Field = {
    components: { Icon },
    props: { label: { type: String, required: true }, help: String, error: [String, Array], required: Boolean, count: String },
    data: () => ({ id: `field-${++fields}` }),
    computed: {
        errors() {
            return [].concat(this.error ?? []).filter(Boolean);
        },
        describedby() {
            return this.errors.length ? `${this.id}-error` : this.help ? `${this.id}-help` : null;
        },
    },
    template: `
        <div class="space-y-1.5">
            <div class="flex items-baseline justify-between gap-3">
                <label :for="id" class="label">{{ label }} <span v-if="!required" class="font-normal text-muted">{{ t('form.optional') }}</span></label>
                <span v-if="count" class="text-sm font-medium text-muted tabular-nums">{{ count }}</span>
                <slot name="aside" />
            </div>
            <slot :id="id" :describedby="describedby" :invalid="errors.length ? 'true' : null" />
            <p v-if="errors.length" :id="id + '-error'" class="field-error" role="alert">
                <Icon name="alert" :size="16" class="mt-px shrink-0" /><span>{{ errors.join(' ') }}</span>
            </p>
            <p v-else-if="help" :id="id + '-help'" class="help">{{ help }}</p>
        </div>`,
};

const TONES = {
    good: { color: 'text-good', icon: 'check' },
    muted: { color: 'text-muted', icon: 'dot' },
    warn: { color: 'text-warn', icon: 'alert' },
    bad: { color: 'text-bad', icon: 'alert' },
    info: { color: 'text-accent', icon: 'info' },
};

/** Where something stands: an icon and words, so it never rests on colour alone. */
export const StatusChip = {
    components: { Icon },
    props: { tone: { type: String, default: 'muted' }, label: { type: String, required: true } },
    computed: {
        look: (vm) => TONES[vm.tone] ?? TONES.muted,
    },
    template: `<span class="chip" :class="look.color"><Icon :name="look.icon" :size="14" /> {{ label }}</span>`,
};

/** A plain empty state: an icon, what's missing, what to do, and an optional action in the slot. */
export const EmptyState = {
    components: { Icon },
    props: { icon: { type: String, default: 'list' }, title: { type: String, required: true }, body: String },
    template: `
        <div class="card px-6 py-12 text-center">
            <Icon :name="icon" :size="32" class="mx-auto text-muted" />
            <p class="mt-3 text-lg font-bold text-cream">{{ title }}</p>
            <p v-if="body" class="mx-auto mt-1 max-w-prose text-muted">{{ body }}</p>
            <div v-if="$slots.default" class="mt-4"><slot /></div>
        </div>`,
};

/** A message in the page: what happened and what to do, with an icon (role status, or alert for problems). */
export const Notice = {
    components: { Icon },
    props: { tone: { type: String, default: 'info' } },
    computed: {
        look: (vm) => TONES[vm.tone] ?? TONES.info,
    },
    template: `
        <div class="flex gap-3 rounded-2xl border border-line bg-raised p-3.5 text-sm text-cream" :role="tone === 'bad' ? 'alert' : 'status'">
            <Icon :name="look.icon" :size="18" class="mt-0.5 shrink-0" :class="look.color" />
            <div class="min-w-0 flex-1"><slot /></div>
        </div>`,
};
