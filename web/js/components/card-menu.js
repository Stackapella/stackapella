// A song card's More (⋯) menu (Hillel: "put delete in a drop toggle with more actions like share,
// invite, etc"): the actions that exist for that card, the destructive one last and in red. Escape
// and a tap outside close it; focus goes back to the button. The card places it (absolute), which
// also anchors its list.

import { Icon } from './icon.js';

export const CardMenu = {
    components: { Icon },
    props: {
        label: { type: String, required: true }, // its accessible name, with the song's title
        items: { type: Array, required: true }, // [{ key, label, run, danger }]
    },
    data: () => ({ open: false }),
    template: `
        <div :class="open ? 'is-on' : ''" @keydown.esc.stop="close(true)">
            <button ref="button" type="button" class="tap-44 grid size-9 place-items-center rounded-full text-muted hover:bg-raised hover:text-cream" :aria-label="label" aria-haspopup="menu" :aria-expanded="open ? 'true' : 'false'" data-card-menu @click.stop="open = !open">
                <Icon name="more" :size="20" />
            </button>
            <ul v-if="open" class="absolute end-0 top-full z-30 mt-1 min-w-48 rounded-2xl border border-edge bg-panel p-1 shadow-xl" role="menu" :aria-label="label">
                <li v-for="item in items" :key="item.key" role="none">
                    <button type="button" role="menuitem" class="flex min-h-11 w-full items-center rounded-xl px-3 text-start text-sm font-semibold hover:bg-raised" :class="item.danger ? 'text-record' : 'text-cream'" :data-menu-item="item.key" @click.stop="choose(item)">{{ item.label }}</button>
                </li>
            </ul>
        </div>`,
    watch: {
        open(now) {
            if (now) this.$nextTick(() => this.$el.querySelector('[role=menuitem]')?.focus());
        },
    },
    mounted() {
        this.away = (event) => {
            if (this.open && !this.$el.contains(event.target)) this.close(false);
        };
        document.addEventListener('pointerdown', this.away);
    },
    unmounted() {
        document.removeEventListener('pointerdown', this.away);
    },
    methods: {
        close(refocus) {
            this.open = false;
            if (refocus) this.$refs.button?.focus();
        },
        choose(item) {
            this.close(true);
            item.run();
        },
    },
};
