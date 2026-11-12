// The song library's filters, over Home's Browse all (js/library-filter.js sorts and filters): a title search and the
// part you sing on one row, then the categories as one row of chips with their counts (in season first), which scrolls
// sideways when it runs out of room (docs/design-rules.md: chips fit one row or scroll with a fade, never one alone on a
// second line). Light on purpose: no new screen, nothing that isn't the library's own list.

import { t, tk } from '../i18n.js';
import { MORE } from '../library-filter.js';
import { Icon } from './icon.js';

export const GROUP_WORDS = {
    christmas: tk('home.songs.group.christmas'),
    'jewish-songs': tk('home.songs.group.jewish'),
    rounds: tk('home.songs.group.rounds'),
    spirituals: tk('home.songs.group.spirituals'),
    hymns: tk('home.songs.group.hymns'),
    'sea-shanties': tk('home.songs.group.shanties'),
    'folk-songs': tk('home.songs.group.folk'),
    classical: tk('home.songs.group.classical'),
    'barbershop-songs': tk('home.songs.group.barbershop'),
    [MORE]: tk('home.songs.group.more'),
};
const VOICE_WORDS = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), bass: tk('parts.bass') };

export const LibraryFilters = {
    components: { Icon },
    // `groups`: groupCounts(); `total`: every song; `filters`: { group, voice, query } (v-model).
    props: { groups: { type: Array, required: true }, total: { type: Number, required: true }, filters: { type: Object, required: true } },
    emits: ['update:filters'],
    setup: () => ({ GROUP_WORDS, VOICE_WORDS }),
    data: () => ({ overflowing: false }),
    template: `
        <div class="mb-4 space-y-3" data-library-filters>
            <div class="flex flex-wrap gap-2">
                <label class="relative min-w-0 flex-[1_1_12rem]">
                    <span class="sr-only">{{ t('home.songs.filter.searchLabel') }}</span>
                    <Icon name="search" :size="18" class="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-muted" />
                    <input type="search" class="input ps-11" :value="filters.query" :placeholder="t('home.songs.filter.search', { count: total })" autocomplete="off" enterkeyhint="search" data-library-search @input="set({ query: $event.target.value })" />
                </label>
                <label class="flex shrink-0 items-center gap-2">
                    <span class="text-sm font-semibold text-muted">{{ t('home.songs.filter.iSing') }}</span>
                    <select class="input w-auto" :value="filters.voice ?? ''" data-library-voice @change="set({ voice: $event.target.value || null })">
                        <option value="">{{ t('home.songs.filter.anyPart') }}</option>
                        <option v-for="(words, voice) in VOICE_WORDS" :key="voice" :value="voice">{{ t(words) }}</option>
                    </select>
                </label>
            </div>
            <div ref="row" class="-mx-4 flex scroll-px-4 gap-2 overflow-x-auto px-4 [scrollbar-width:none] md:mx-0 md:scroll-px-0 md:px-0" :class="overflowing ? '[mask-image:linear-gradient(90deg,#000_calc(100%-2.5rem),transparent)] rtl:[mask-image:linear-gradient(270deg,#000_calc(100%-2.5rem),transparent)]' : ''" role="group" :aria-label="t('home.songs.filter.groups')" data-library-groups>
                <button type="button" class="filter-chip shrink-0" :aria-pressed="!filters.group" data-library-group="" @click="set({ group: null })">{{ t('home.songs.filter.all') }}<span class="tabular-nums opacity-70">{{ total }}</span></button>
                <button v-for="g in groups" :key="g.key" type="button" class="filter-chip shrink-0" :aria-pressed="filters.group === g.key" :data-library-group="g.key" :data-season="g.season ? '' : null" @click="set({ group: filters.group === g.key ? null : g.key })">{{ t(GROUP_WORDS[g.key] ?? GROUP_WORDS.more) }}<span class="tabular-nums opacity-70">{{ g.count }}</span></button>
            </div>
        </div>`,
    mounted() {
        this.measure = () => (this.overflowing = !!this.$refs.row && this.$refs.row.scrollWidth > this.$refs.row.clientWidth + 1);
        this.observer = typeof ResizeObserver === 'function' ? new ResizeObserver(this.measure) : null;
        this.observer?.observe(this.$refs.row);
        this.measure();
    },
    updated() {
        this.measure?.();
    },
    watch: {
        /** The chosen chip in sight: on a phone the row scrolls, and the one that's on may be past its edge. */
        'filters.group'() {
            this.$nextTick(() => this.$refs.row?.querySelector('[aria-pressed=true]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
        },
    },
    beforeUnmount() {
        this.observer?.disconnect();
    },
    methods: {
        set(change) {
            this.$emit('update:filters', { ...this.filters, ...change });
        },
    },
};
