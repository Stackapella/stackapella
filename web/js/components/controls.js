// The shared controls of the forms system (Hillel, 2026-10-29: "I like new design system, I think it
// works well for game mode on or off"; ~/Code/shots-review/designs/forms/NOTES.md). The looks are the
// utilities in css/input.css (switch-track, seg, stepper, range, checkbox); these add the behaviour
// and the accessible names. The styleguide (/styleguide) shows each in every state.

import { Icon } from './icon.js';

let ids = 0;

/**
 * A switch, with its label and an optional line under it: the whole row is the role="switch"
 * button (a 44px target), named by the label and described by the hint. `tag` is a small word
 * beside the label ("Plus"). Without `label` it's the bare switch, named by `aria-label`.
 */
export const Switch = {
    props: { on: Boolean, label: { type: String, default: '' }, hint: { type: String, default: '' }, tag: { type: String, default: '' }, disabled: Boolean },
    emits: ['change'],
    data: () => ({ id: `switch-${++ids}` }),
    template: `
        <button v-if="label" type="button" role="switch" :aria-checked="on ? 'true' : 'false'" :aria-labelledby="id" :aria-describedby="hint ? id + '-hint' : null" :disabled="disabled" class="flex min-h-11 w-full items-center gap-4 text-start disabled:cursor-not-allowed disabled:opacity-[0.42]" @click="$emit('change', !on)">
            <span class="min-w-0 flex-1">
                <span :id="id" class="flex items-center gap-2 font-semibold text-cream">{{ label }}<span v-if="tag" class="chip">{{ tag }}</span></span>
                <span v-if="hint" :id="id + '-hint'" class="block text-[0.8125rem] leading-snug text-muted">{{ hint }}</span>
            </span>
            <span class="switch-track"><span class="switch-knob"></span></span>
        </button>
        <button v-else type="button" role="switch" :aria-checked="on ? 'true' : 'false'" :disabled="disabled" class="grid h-11 w-13 shrink-0 place-items-center disabled:cursor-not-allowed disabled:opacity-[0.42]" @click="$emit('change', !on)">
            <span class="switch-track"><span class="switch-knob"></span></span>
        </button>`,
};

/**
 * A segmented control: one choice of a few, as a radiogroup on a tinted track. `options` are
 * `{ value, label }`; the arrow keys move the choice (and the focus), Home and End go to the ends.
 * `full` stretches it across its row, each segment an equal share.
 */
export const Segmented = {
    props: { modelValue: { type: [String, Number, Boolean], default: null }, options: { type: Array, required: true }, label: { type: String, default: '' }, labelledby: { type: String, default: '' }, full: Boolean, disabled: Boolean },
    emits: ['update:modelValue'],
    template: `
        <div class="seg" :class="[full ? 'flex w-full' : '', disabled ? 'pointer-events-none opacity-45' : '']" role="radiogroup" :aria-label="label || null" :aria-labelledby="labelledby || null" :aria-disabled="disabled ? 'true' : null" @keydown="key">
            <button v-for="(option, i) in options" :key="String(option.value)" ref="option" type="button" role="radio" class="seg-option" :class="full ? 'flex-1 px-2.5' : ''" :aria-checked="option.value === modelValue ? 'true' : 'false'" :tabindex="i === current ? 0 : -1" :disabled="disabled" @click="$emit('update:modelValue', option.value)">{{ option.label }}</button>
        </div>`,
    computed: {
        /** The one segment in the tab order: the chosen one, or the first. */
        current() {
            return Math.max(0, this.options.findIndex((option) => option.value === this.modelValue));
        },
    },
    methods: {
        key(event) {
            const rtl = getComputedStyle(this.$el).direction === 'rtl';
            const step = { ArrowRight: rtl ? -1 : 1, ArrowDown: 1, ArrowLeft: rtl ? 1 : -1, ArrowUp: -1 }[event.key];
            const last = this.options.length - 1;
            const to = event.key === 'Home' ? 0 : event.key === 'End' ? last : step ? (this.current + step + this.options.length) % this.options.length : null;
            if (to === null) return;
            event.preventDefault();
            this.$emit('update:modelValue', this.options[to].value);
            this.$refs.option[to]?.focus();
        },
    },
};

/**
 * A stepper (− value +): a number typed or stepped, kept within `min` and `max`, with − or + off at
 * its limit. `label` names the number for screen readers; `unit` shows beside it ("BPM").
 */
export const Stepper = {
    components: { Icon },
    props: { modelValue: { type: Number, required: true }, min: { type: Number, default: 0 }, max: { type: Number, default: 100 }, step: { type: Number, default: 1 }, label: { type: String, required: true }, unit: { type: String, default: '' }, decrease: { type: String, required: true }, increase: { type: String, required: true }, disabled: Boolean },
    emits: ['update:modelValue'],
    template: `
        <div class="stepper" :class="disabled ? 'opacity-45' : ''">
            <button type="button" :aria-label="decrease" :disabled="disabled || modelValue <= min" @click="set(modelValue - step)"><Icon name="minus" :size="18" /></button>
            <input type="text" inputmode="numeric" :value="modelValue" :aria-label="label" :disabled="disabled" @change="set(Number($event.target.value))" @keydown.up.prevent="set(modelValue + step)" @keydown.down.prevent="set(modelValue - step)" />
            <span v-if="unit" class="me-1.5 text-[0.8125rem] text-muted">{{ unit }}</span>
            <button type="button" :aria-label="increase" :disabled="disabled || modelValue >= max" @click="set(modelValue + step)"><Icon name="plus" :size="18" /></button>
        </div>`,
    methods: {
        set(value) {
            const kept = Number.isFinite(value) ? Math.min(this.max, Math.max(this.min, Math.round(value / this.step) * this.step)) : this.modelValue;
            this.$emit('update:modelValue', kept);
            // A typed value that was out of range shows the kept one.
            this.$el.querySelector('input').value = kept;
        },
    },
};

/** A slider in the accent, filled up to its value. Every other attribute (aria-label, id) goes on the input. */
export const Range = {
    props: { modelValue: { type: Number, required: true }, min: { type: Number, default: 0 }, max: { type: Number, default: 100 }, step: { type: Number, default: 1 } },
    emits: ['update:modelValue'],
    template: `<input type="range" class="range" :min="min" :max="max" :step="step" :value="modelValue" :style="{ '--p': fill }" @input="$emit('update:modelValue', Number($event.target.value))" />`,
    computed: {
        fill() {
            return `${((this.modelValue - this.min) / (this.max - this.min || 1)) * 100}%`;
        },
    },
};

/** A checkbox in its row, with the label and an optional line under it. */
export const Checkbox = {
    props: { modelValue: Boolean, label: { type: String, required: true }, hint: { type: String, default: '' }, disabled: Boolean, invalid: Boolean },
    emits: ['update:modelValue'],
    data: () => ({ id: `check-${++ids}` }),
    template: `
        <label class="check-row">
            <input type="checkbox" class="checkbox" :checked="modelValue" :disabled="disabled" :aria-invalid="invalid ? 'true' : null" :aria-describedby="hint ? id : null" @change="$emit('update:modelValue', $event.target.checked)" />
            <span class="min-w-0"><span class="block">{{ label }}</span><span v-if="hint" :id="id" class="block text-[0.8125rem] font-normal text-muted">{{ hint }}</span></span>
        </label>`,
};

/**
 * The arrow keys in every radiogroup of role="radio" buttons (the forms system's segmented
 * controls and pill choices): left/up and right/down move to the next enabled choice and pick it,
 * Home and End go to the ends. A group with its own keys (Segmented, the theme sheet) handles them
 * first and stops them here.
 */
export function radioArrows(root = document) {
    root.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        const radio = event.target.closest?.('[role="radio"]');
        const group = radio?.closest('[role="radiogroup"]');
        if (!group) return;
        const rtl = getComputedStyle(group).direction === 'rtl';
        const step = { ArrowRight: rtl ? -1 : 1, ArrowDown: 1, ArrowLeft: rtl ? 1 : -1, ArrowUp: -1 }[event.key];
        if (!step && event.key !== 'Home' && event.key !== 'End') return;
        const radios = [...group.querySelectorAll('[role="radio"]')].filter((r) => r.closest('[role="radiogroup"]') === group && !r.disabled);
        const at = radios.indexOf(radio);
        if (at < 0 || !radios.length) return;
        const to = event.key === 'Home' ? 0 : event.key === 'End' ? radios.length - 1 : (at + step + radios.length) % radios.length;
        event.preventDefault();
        radios[to].focus();
        radios[to].click();
    });
}

/**
 * v-fill on a slider (`<input type="range" class="range" v-fill>`): keeps the accent fill (--p) at
 * its value, as it's dragged and when the value changes from outside. Registered app-wide in main.js.
 */
export const fill = {
    mounted(el) {
        el.__fill = () => {
            const min = Number(el.min || 0);
            const max = Number(el.max || 100);
            el.style.setProperty('--p', `${((Number(el.value) - min) / (max - min || 1)) * 100}%`);
        };
        el.__fill();
        el.addEventListener('input', el.__fill);
    },
    updated(el) {
        el.__fill?.();
    },
    unmounted(el) {
        el.removeEventListener('input', el.__fill);
    },
};
