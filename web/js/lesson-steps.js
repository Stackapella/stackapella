// The lesson steps beyond the free course's own (docs/plus-courses-build-plan.md, 6a): each kind's
// component, and whether its result gives the lesson's stars (`scored`) or it's done once it's
// done (`scored: false`, as Learn and Use are). The lesson view (views/learn.js) mounts whatever is
// registered here with the same props and events as an exercise, so a course agent adds a step type
// by adding one line here (with the lead), never by editing the lesson view.

//
// Each component loads when a lesson needs it (the free course uses none of them), so a first visit's
// lesson 1 doesn't download them (tests/flows/slow_phone.py); prefetchSteps() fetches a lesson's own
// as it opens, so a step never waits for its code.

import { defineAsyncComponent } from 'vue';
import { tk } from './i18n.js';
import { lazyView } from './lazy.js';

/** A step's component, imported on first use, from the page's own version (js/lazy.js). */
function lazyStep(load) {
    const loader = lazyView(load);
    const component = defineAsyncComponent(loader);
    component.prefetch = loader.prefetch;
    return component;
}

const WaveLane = lazyStep(() => import('./components/wave-lane.js').then((m) => m.WaveLane));
const Choose = lazyStep(() => import('./components/choose.js').then((m) => m.Choose));
const BullseyeStep = lazyStep(() => import('./components/bullseye.js').then((m) => m.BullseyeStep));
const SelfCheck = lazyStep(() => import('./components/selfcheck.js').then((m) => m.SelfCheck));
const BreathHold = lazyStep(() => import('./components/breath-hold.js').then((m) => m.BreathHold));
/** The staff an exercise is read from (components/exercise.js), loaded the same way. */
export const ReadStave = lazyStep(() => import('./components/read-stave.js').then((m) => m.ReadStave));

export const STEPS = {
    wave: { component: WaveLane, scored: true }, // the trace lane (the Vibrato course)
    // `hint`: the footer's words while the step isn't done yet (else "Sing it once to go on").
    choose: { component: Choose, scored: true, hint: { goOn: tk('choose.toGoOn'), finish: tk('choose.toFinish') } }, // listen and choose (no mic)
    bullseye: { component: BullseyeStep, scored: true }, // echo a note, see where it lands (Perfect your pitch)
    selfcheck: { component: SelfCheck, scored: false, hint: { goOn: tk('selfcheck.toGoOn'), finish: tk('selfcheck.toFinish') } }, // what we can't hear yet: hear, record, compare, ask
    breathhold: { component: BreathHold, scored: true, hint: { goOn: tk('breathhold.toGoOn'), finish: tk('breathhold.toFinish') } }, // a long note that ends when you stop (Breath and stamina)
};

/** The registered step for `kind`, or null (the free course's own kinds are the lesson view's). */
export function stepOf(kind) {
    return STEPS[kind] ?? null;
}

/** Whether a step of `kind` gives the lesson its stars: the free course's play and record, or a registered scored one. */
export function isScored(kind) {
    return kind === 'play' || kind === 'record' || !!STEPS[kind]?.scored;
}

/** Fetches ahead the step components `lesson` uses (its registered kinds, and the staff), once it opens. */
export function prefetchSteps(lesson) {
    for (const step of lesson?.steps ?? []) {
        STEPS[step.kind]?.component.prefetch();
        if (step.exercise?.staff) ReadStave.prefetch();
    }
}
