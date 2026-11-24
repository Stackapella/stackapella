// The choir wait's banner (docs/decisions.md, 2026-11-24; the coordinator's spec for Hillel: "auto-start after the take,
// a banner while it's made, email when ready"): one line under the app bar, in the page's flow, while a choir made from
// their singing is in line, being made, ready or failed. The server's state (so it survives closing the app), looked at
// every 15 s while it shows and the song is on its way. One song at a time: ready first, then failed, then on its way.
// It hides on that song's own choir page and on its take review (Sing sets ownChoir.reviewing), while Sing records,
// counts in or plays (`quiet`), and once closed until its state changes. Serious: the surface, a hairline and an 8 px
// dot; game: an arcade panel (css/input.css, [data-choir-banner]).

import { account } from '../stores/account.js';
import { aiOn } from '../ai-choice.js';
import { bannerRequest, bannerState, loadOwnChoir, ownChoir, ownChoirOn } from '../own-choir-state.js';
import { local } from '../storage.js';
import { count } from '../usage.js';
import { navigate } from '../router.js';
import { songTitle } from '../song-titles.js';
import { t, tk } from '../i18n.js';
import { Icon } from './icon.js';

const CLOSED = 'stackapella.choirBannerClosed';
const POLL_MS = 15000;
// Each state's words and its one action (choirWait.banner.*).
const WORDS = { queued: tk('choirWait.banner.queued'), queuedSoon: tk('choirWait.banner.queuedSoon'), tomorrow: tk('choirWait.banner.tomorrow'), making: tk('choirWait.banner.making'), slow: tk('choirWait.banner.slow'), ready: tk('choirWait.banner.ready'), failed: tk('choirWait.banner.failed') };
const ACTIONS = { ready: tk('choirWait.banner.listen'), failed: tk('choirWait.banner.why') };
const SEE = tk('choirWait.banner.see');

export const ChoirBanner = {
    components: { Icon },
    props: {
        quiet: Boolean, // Sing while it records, counts in or plays: nothing over the singing
        inline: Boolean, // inside a header that has its own gutters (Sing's), not on the page's grid
    },
    data: () => ({ closed: local.get(CLOSED), poll: null }),
    setup: () => ({ ownChoir }),
    computed: {
        request() {
            if (!account.me || !ownChoirOn() || !aiOn()) return null; // AI off: no banner (the spec)
            return bannerRequest();
        },
        state: (vm) => bannerState(vm.request),
        /** What closing it remembers: the song and its state (a new state shows it again). */
        key: (vm) => (vm.request ? `${vm.request.id}:${vm.state}` : null),
        shown() {
            const r = this.request;
            if (!r || this.quiet || this.closed === this.key) return false;
            if (ownChoir.reviewing === r.id) return false; // its take review says it all
            return location.pathname !== `/t/${r.templateId}/choir` && !/^\/(signin|signup|register)/.test(location.pathname);
        },
        song: (vm) => songTitle({ id: vm.request?.templateId, title: vm.request?.title }),
        words() {
            const r = this.request;
            const ready = (r?.progress ?? []).filter((p) => p.status === 'ready').length;
            const which = this.state === 'queued' && r?.etaMinutes == null ? 'queuedSoon' : this.state;
            return t(WORDS[which], { song: this.song, m: r?.etaMinutes ?? 0, n: ready, count: (r?.progress ?? []).length || 4 });
        },
        action: (vm) => t(ACTIONS[vm.state] ?? SEE),
        dot: (vm) => (vm.state === 'ready' ? 'bg-good' : vm.state === 'failed' || vm.state === 'slow' ? 'bg-warn' : 'bg-accent'),
        /** On its way (not ready, not failed): looked at again every 15 s while it shows. */
        polling: (vm) => vm.shown && !['ready', 'failed'].includes(vm.state),
    },
    watch: {
        polling: {
            immediate: true,
            handler(on) {
                clearInterval(this.poll);
                this.poll = on ? setInterval(() => loadOwnChoir({ force: true }).catch((error) => console.info('The choir was not checked', error?.message ?? error)), POLL_MS) : null;
            },
        },
    },
    created() {
        if (account.me && ownChoirOn() && !ownChoir.loaded) loadOwnChoir().catch((error) => console.info('The choir was not loaded for its banner', error?.message ?? error));
    },
    beforeUnmount() {
        clearInterval(this.poll);
    },
    methods: {
        open() {
            count(`choir_banner_tap_${this.state}`);
            navigate(`/t/${encodeURIComponent(this.request.templateId)}/choir`);
        },
        close() {
            count('choir_banner_closed');
            this.closed = this.key;
            local.set(CLOSED, this.key);
        },
    },
    template: `
        <div v-if="shown" :class="inline ? 'w-full basis-full' : 'app-wrap'" role="status" :aria-label="t('choirWait.banner.label')" data-choir-banner :data-state="state">
            <div class="flex min-h-11 items-center gap-3 rounded-[14px] border border-edge bg-panel ps-4 pe-1" data-ui="choir-banner">
                <span class="size-2 shrink-0 rounded-full" :class="dot" aria-hidden="true"></span>
                <p class="min-w-0 flex-1 py-2 text-sm leading-snug text-cream line-clamp-2" data-choir-banner-words>{{ words }}</p>
                <button type="button" class="link tap-44 shrink-0 text-sm font-semibold" data-choir-banner-open @click="open">{{ action }}</button>
                <button type="button" class="icon-btn-quiet shrink-0" :aria-label="t('choirWait.banner.close')" data-choir-banner-close @click="close"><Icon name="close" :size="18" /></button>
            </div>
        </div>`,
};
