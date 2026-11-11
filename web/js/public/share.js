// A public page's Share button (site/_share.blade.php; docs/retention-plan.md, "Sharing"): the device's share sheet
// where there is one, else the link copied. The link carries ?ref=share-<surface>, the surface's fixed tag (never who
// shared or anything of theirs), so the friends it brings count as its arrivals; the share counts as
// sh:<surface>.<method>. Nothing else is sent or kept.
import { countAnon } from './count.js';

const COPIED_FOR = 2000; // ms the label says the link is copied (a label change, not audio: a timer is fine here)

function link(button) {
    const url = new URL(button.dataset.url || location.href, location.origin);
    url.searchParams.set('ref', `share-${button.dataset.surface}`);
    return url.toString();
}

async function share(button) {
    const url = link(button);
    const surface = button.dataset.surface;

    if (navigator.share) {
        try {
            await navigator.share({ title: button.dataset.title, text: button.dataset.text, url });
            countAnon(`sh:${surface}.sheet`);
        } catch (error) {
            // Closed without sharing (AbortError) shares nothing; anything else falls back to copying.
            if (error?.name !== 'AbortError') await copy(button, url, surface);
        }
        return;
    }
    await copy(button, url, surface);
}

async function copy(button, url, surface) {
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // No clipboard (an old browser, or not allowed): the address bar's own link is the fallback, so say nothing.
        return;
    }
    countAnon(`sh:${surface}.copy`);
    const label = button.querySelector('[data-share-label]');
    if (!label) return;
    const before = label.textContent;
    // The icon alone (a narrow row): its name says it instead, for a screen reader and a tooltip.
    if (label.hidden) {
        button.title = button.dataset.copied;
        button.setAttribute('aria-label', button.dataset.copied);
        setTimeout(() => { button.title = before; button.setAttribute('aria-label', before); }, COPIED_FOR);
        return;
    }
    label.textContent = button.dataset.copied;
    setTimeout(() => { label.textContent = before; }, COPIED_FOR);
}

// Wide enough from the start for "Link copied" as well as "Share", so saying it never moves the row. Beside the button
// before it if it fits; else the icon alone beside it; and only where even that can't fit (the narrowest phones), the
// whole label on a line of its own.
function fit(button) {
    const label = button.querySelector('[data-share-label]');
    if (!label || !button.dataset.copied) return;
    const beside = button.previousElementSibling;
    const below = () => !!beside && button.offsetTop > beside.offsetTop + beside.offsetHeight / 2;
    const show = (whole) => {
        label.hidden = !whole;
        button.style.minWidth = '';
        if (whole) {
            button.removeAttribute('aria-label');
            button.removeAttribute('title');
            const before = label.textContent;
            const plain = button.getBoundingClientRect().width;
            label.textContent = button.dataset.copied;
            const copied = button.getBoundingClientRect().width;
            label.textContent = before;
            button.style.minWidth = `${Math.ceil(Math.max(plain, copied))}px`;
        } else {
            button.title = label.textContent;
            button.setAttribute('aria-label', label.textContent);
        }
    };

    button.style.flex = '';
    button.style.justifyContent = '';
    show(true);
    if (below()) {
        show(false);
        if (below()) {
            show(true);
            // Under a button that fills its row (a hero's on a phone), as wide as it, as the hero's own buttons are.
            if (beside && beside.offsetWidth >= button.parentElement.clientWidth - 1) {
                button.style.flex = '1 1 100%';
                button.style.justifyContent = 'center';
            }
        }
    }
    button.dataset.fit = '1';
}

const buttons = [...document.querySelectorAll('[data-share]')];
const refit = () => buttons.forEach(fit);
for (const button of buttons) button.addEventListener('click', () => share(button));
refit();
// Again whenever the button beside it changes size (the page's fonts arriving, the skin switching, the window's
// width): the end state is the same each time, so this settles at once.
const watch = new ResizeObserver(refit);
for (const button of buttons) if (button.previousElementSibling) watch.observe(button.previousElementSibling);
addEventListener('resize', refit);
