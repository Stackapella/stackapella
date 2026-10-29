// Keep this as a song (js/keep-song.js): under a finale's lesson end, in both skins, an offer to keep
// the lesson's takes and voices as a song in Sing, then a way to open it there. Plain words only: it's
// a song of your own, not a reward.

import { localized, t } from '../i18n.js';
import { navigate } from '../router.js';
import { canKeep, keepAsSong } from '../keep-song.js';
import { LimitReached } from '../stores/projects.js';
import { count } from '../usage.js';
import { PlusMoment } from './plus-moment.js';

export const KeepSong = {
    components: { PlusMoment },
    props: {
        lesson: { type: Object, required: true },
        kept: { type: Object, required: true },
        base: { type: Number, required: true },
    },
    data: () => ({ state: 'idle', projectId: null, limitOpen: false }),
    template: `
        <section v-if="ready" class="rounded-3xl border border-edge bg-panel p-4 md:p-5" :aria-label="heading" data-keep-song>
            <p class="font-bold text-cream">{{ heading }}</p>
            <p class="mt-1 text-sm text-muted">{{ state === 'kept' ? t('keepSong.kept') : state === 'error' ? t('keepSong.error') : t('keepSong.line') }}</p>
            <div class="mt-3 flex flex-wrap gap-3" aria-live="polite">
                <button v-if="state !== 'kept'" type="button" class="btn-primary" :disabled="state === 'busy'" @click="keep" data-keep-song-button>{{ state === 'busy' ? t('keepSong.keeping') : t('keepSong.keep') }}</button>
                <button v-else type="button" class="btn-primary" @click="open" data-keep-song-open>{{ t('keepSong.open') }}</button>
            </div>
            <!-- A full free plan: the same Plus moment as Home's New song. -->
            <PlusMoment v-if="limitOpen" :title="t('plus.moment.projectTitle')" :text="t('plus.moment.projectLimit')" full from="course_keep" @close="limitOpen = false" />
        </section>`,
    setup: () => ({ t }),
    computed: {
        ready: (vm) => canKeep(vm.lesson, vm.kept),
        heading: (vm) => localized(vm.lesson.keepSong.title), // what you'd keep, by its song's name; the button is the action
    },
    methods: {
        async keep() {
            this.state = 'busy';
            try {
                const project = await keepAsSong(this.lesson, this.kept, this.base);
                this.projectId = project.id;
                this.state = 'kept';
                count('keep_song');
            } catch (error) {
                if (error instanceof LimitReached) {
                    this.state = 'idle';
                    this.limitOpen = true;
                    return;
                }
                console.error('The finale was not kept', error);
                this.state = 'error';
            }
        },
        open() {
            navigate(`/p/${this.projectId}`);
        },
    },
};
