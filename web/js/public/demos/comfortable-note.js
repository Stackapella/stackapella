// Lesson 1's first step as a slotted demo (the Course feature page and Beginners; docs/demo-pages-plan.md): find
// your comfortable note with the app's own card (js/public/voice-capture.js; Hillel: one component per task, never
// a second variant). The start mounts the card over the still frame; the mic opens only when the visitor taps the
// card's Start, the pitch is worked out on this device, and nothing is recorded or sent. A refused or missing mic
// says why, plainly. The words are the view's (the frame's JSON and data-*).

import { mountCapture } from '../voice-capture.js';

export async function mount(stage) {
    const root = stage.querySelector('[data-capture]');
    const words = JSON.parse(root.querySelector('script[data-capture-words]').textContent);
    const card = root.querySelector('[data-capture-card]');
    const say = root.querySelector('[data-capture-say]');
    const why = { denied: root.dataset.whyDenied, 'no-mic': root.dataset.whyNoMic, unsupported: root.dataset.whyUnsupported };
    card.replaceChildren();
    mountCapture(card, {
        words,
        color: 'var(--color-part-1)',
        onFound: () => { say.textContent = ''; },
        onError: (reason) => { say.textContent = why[reason] ?? why.unsupported; },
    });
}
