// The shared demo slot's start (server/resources/views/components/site/demo.blade.php): one press
// only. The tap makes the AudioContext (as Safari asks), shows the loading state at once, loads the
// demo's own module (nothing before the tap), and hands it the stage: `mount(stage, { slot })`, which
// draws the live demo over the still frame and may return a promise. A demo that fails to load says
// so in its caption, plainly; the button never comes back for a second, overlapping start. Once it's
// live, the focus the start had moves to the demo's first control.

import { unlock } from './demo-player.js';

for (const slot of document.querySelectorAll('[data-demo-src]')) {
    const button = slot.querySelector('[data-demo-start]');
    button?.addEventListener('click', async () => {
        if (slot.dataset.state !== 'idle') return;
        const had = document.activeElement === button; // before disabling it, which drops the focus
        slot.dataset.state = 'loading';
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        unlock();
        try {
            const demo = await import(new URL(slot.dataset.demoSrc, location.href).href);
            await demo.mount(slot.querySelector('[data-demo-stage]'), { slot });
            slot.dataset.state = 'live';
            button.remove();
            // The start had the focus: it goes to the demo's first control, not back to the top of the page.
            if (had) slot.querySelector('[data-demo-stage]').querySelector('button:not([disabled]), select, input, [tabindex="0"]')?.focus({ preventScroll: true });
        } catch (error) {
            console.error('The demo could not start', error);
            slot.dataset.state = 'failed';
            button.removeAttribute('aria-busy');
            slot.querySelector('[data-demo-caption]').textContent = slot.dataset.failed;
        }
    });
}
