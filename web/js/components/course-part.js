// Your part (docs/courses/choir-part-sections-plan.md): the four parts as one segmented choice, in score
// order, on a course page under its title and on lesson 1's first card. The first pick is suggested
// from the singer's range (a suggestion, never a label); it can be changed any time and follows the
// account with the course's progress. Game mode reskins the same control (.seg), never a new row.

import { t, tk } from '../i18n.js';
import { Segmented } from './controls.js';
import { learning, knownRange, setCoursePart } from '../stores/learning.js';
import { coursePart, partPicked, partsOf } from '../course-parts.js';
import { voiceType } from '../course.js';

const NAMES = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), bass: tk('parts.bass') };

export const CoursePart = {
    components: { Segmented },
    props: { course: { type: Object, required: true } },
    template: `
        <div v-if="parts" class="space-y-2" data-course-part :data-part="part">
            <p :id="uid" class="text-sm font-semibold text-cream">{{ t('course.part.label') }}</p>
            <Segmented :model-value="part" :options="options" :labelledby="uid" full @update:model-value="pick" />
            <p v-if="suggested" class="text-xs text-muted">{{ t('course.part.suggested') }}</p>
        </div>`,
    data: () => ({ uid: 'course-part-' + Math.random().toString(36).slice(2, 8) }),
    computed: {
        parts: (vm) => partsOf(vm.course),
        part: (vm) => coursePart(vm.course, learning.progress, knownRange()),
        options: (vm) => vm.parts.choose.map((value) => ({ value, label: NAMES[value] ? t(NAMES[value]) : value })),
        /** Not picked yet, and the range chose it: say it's only a suggestion. */
        suggested: (vm) => !partPicked(vm.course, learning.progress) && vm.parts.choose.includes(voiceType(knownRange())),
    },
    methods: {
        pick(part) {
            setCoursePart(this.course, part).catch((error) => console.error('The part was not kept', error));
        },
    },
};
