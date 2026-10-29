// The lesson steps beyond the free course's own (docs/plus-courses-build-plan.md, 6a): each kind's
// component, and whether its result gives the lesson's stars (`scored`) or it's done once it's
// done (`scored: false`, as Learn and Use are). The lesson view (views/learn.js) mounts whatever is
// registered here with the same props and events as an exercise, so a course agent adds a step type
// by adding one line here (with the lead), never by editing the lesson view.

import { tk } from './i18n.js';
import { WaveLane } from './components/wave-lane.js';
import { Choose } from './components/choose.js';
import { BullseyeStep } from './components/bullseye.js';
import { SelfCheck } from './components/selfcheck.js';
import { BreathHold } from './components/breath-hold.js';

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
