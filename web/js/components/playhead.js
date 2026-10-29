// A playhead you can move (Hillel: "I'd like to be able to manage the playhead better"): a red line
// with a handle at the top that you drag (pointer capture, so it follows the pointer off the handle),
// which grows when the pointer is near, and which the keyboard moves as a slider: the arrows by a beat,
// with Shift by a bar, Home to the start and End to the end. It sits in a positioned box that scrolls
// with what it marks (the note editor's lane; the studio's tracks), placed by `x` and read back by
// `beatAt`, both in that box's pixels. It only says where (`seek`); whoever owns it moves the sound.

import { formatNumber } from '../i18n.js';

export const Playhead = {
    props: {
        beat: { type: Number, required: true },
        x: { type: Function, required: true }, // beat -> px in the box
        beatAt: { type: Function, required: true }, // px in the box -> beat
        height: { type: Number, required: true }, // px
        min: { type: Number, default: 0 },
        max: { type: Number, required: true },
        step: { type: Number, default: 1 }, // the arrows: a beat
        bigStep: { type: Number, default: 4 }, // Shift and the arrows: a bar
        label: { type: String, required: true },
        valueText: { type: String, default: null },
    },
    emits: ['seek'],
    data: () => ({ dragging: false }),
    computed: {
        now: (vm) => Math.round(vm.beat * 100) / 100,
    },
    template: `
        <div class="pointer-events-none absolute start-0 top-0 z-10" :style="{ transform: 'translateX(' + x(beat) + 'px)', height: height + 'px' }" data-playhead>
            <div class="absolute inset-y-0 -start-px w-0.5 bg-record"></div>
            <div role="slider" tabindex="0" :aria-label="label" :aria-valuemin="min" :aria-valuemax="max" :aria-valuenow="now" :aria-valuetext="valueText ?? formatNumber(now)"
                class="group pointer-events-auto absolute -start-[22px] -top-1 grid h-8 w-11 touch-none place-items-center outline-none" :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
                data-playhead-handle @pointerdown="down" @pointermove="move" @pointerup="up" @pointercancel="up" @keydown="key">
                <span class="block size-3 rounded-full bg-record ring-offset-2 ring-offset-ink transition-transform group-hover:scale-150 group-focus-visible:scale-150 group-focus-visible:ring-2 group-focus-visible:ring-cream" :class="dragging ? 'scale-150' : ''"></span>
            </div>
        </div>`,
    methods: {
        formatNumber,
        clamp(beat) {
            return Math.min(this.max, Math.max(this.min, beat));
        },
        /** The pointer's beat, from where it is in the box the playhead moves in. */
        at(event) {
            const box = this.$el.offsetParent?.getBoundingClientRect();
            return this.clamp(this.beatAt(event.clientX - (box?.left ?? 0)));
        },
        down(event) {
            this.dragging = true;
            event.currentTarget.setPointerCapture?.(event.pointerId);
            event.preventDefault();
        },
        move(event) {
            if (this.dragging) this.$emit('seek', this.at(event));
        },
        up(event) {
            if (!this.dragging) return;
            this.dragging = false;
            event.currentTarget.releasePointerCapture?.(event.pointerId);
        },
        key(event) {
            const by = event.shiftKey ? this.bigStep : this.step;
            const to = { ArrowRight: this.beat + by, ArrowUp: this.beat + by, ArrowLeft: this.beat - by, ArrowDown: this.beat - by, Home: this.min, End: this.max }[event.key];
            if (to === undefined) return;
            event.preventDefault();
            event.stopPropagation(); // the editor's own arrow keys move notes
            this.$emit('seek', this.clamp(to));
        },
    },
};
