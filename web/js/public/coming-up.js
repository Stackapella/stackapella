// "Coming up" on /songs (server App\Support\Pages\Festivals and SongsHub::next; Hillel, 2026-11-12: "have the main songs
// page automatically highlight which section is coming up next"). The page is cached, so the festival is picked again
// here from the visitor's own date, by the server's rules: a festival's window opens `ahead` days before its first day
// and closes after its last; of those open, one on now comes first, then the nearest. The first is shown as its card,
// the second as a smaller line, and the first's collection panel moves to the front, the rest in their usual order.
// Nothing about the visitor is asked for, kept or sent. Without the script, the server's pick for its own day stands.

const day = (ymd) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d);
};

/** Today's date at midnight, in the visitor's own time. */
export const midnight = (now) => new Date(now.getFullYear(), now.getMonth(), now.getDate());

/**
 * A festival on `today` (a local midnight): days until its first day (0 on it, negative after), whether it's on, and
 * whether its window is open. `f` is { from, until (Y-m-d), ahead (days), evening (begins at sundown) }.
 */
export function state(today, f) {
    // Rounded: a clock change in between doesn't shift a day.
    const days = Math.round((day(f.from) - today) / 864e5);
    const ended = today > day(f.until);
    return { days, on: !ended && (days < 0 || (days === 0 && !f.evening)), open: f.ahead > 0 && !ended && days <= f.ahead };
}

/** The indexes of the festivals open on `today`: those on now first, then the nearest. */
export function pick(today, festivals) {
    return festivals
        .map((f, i) => ({ i, f, s: state(today, f) }))
        .filter(({ s }) => s.open)
        .sort((a, b) => Number(b.s.on) - Number(a.s.on) || day(a.f.from) - day(b.f.from))
        .map(({ i }) => i);
}

/** How soon, in the card's words: `t` is { one, other ('#' for the count), tonight, today }; '' while it's on. */
export function when(f, s, t) {
    if (s.on) return '';
    if (s.days === 0) return f.evening ? t.tonight : t.today;
    return (s.days === 1 ? t.one : t.other).replace('#', String(s.days));
}

const box = document.querySelector('[data-coming-up]');

if (box) {
    const today = midnight(new Date());
    const cards = [...box.querySelectorAll('[data-festival]')];
    const festivals = cards.map((c) => ({ from: c.dataset.from, until: c.dataset.until, ahead: Number(c.dataset.ahead), evening: c.hasAttribute('data-evening') }));
    const order = pick(today, festivals);
    cards.forEach((card, i) => {
        const s = state(today, festivals[i]);
        const t = { one: card.dataset.tOne, other: card.dataset.tOther, tonight: card.dataset.tTonight, today: card.dataset.tToday };
        card.hidden = i !== order[0];
        card.querySelector('[data-kick]').textContent = s.on ? card.dataset.tKickOn : card.dataset.tKick;
        const soon = card.querySelector('[data-when]');
        soon.textContent = when(festivals[i], s, t);
        soon.hidden = s.on;
        card.querySelector('[data-start]').hidden = s.on;
        card.querySelector('[data-until]').hidden = !s.on;
        const also = box.querySelector(`[data-also="${i}"]`);
        if (also) {
            also.hidden = i !== order[1];
            also.querySelector('[data-when]').textContent = s.on ? card.querySelector('[data-until]').textContent : when(festivals[i], s, t);
        }
    });
    box.hidden = order.length === 0;
    box.dataset.pick = order.length ? cards[order[0]].dataset.key : '';

    // That festival's collection panel first, the rest in their usual order.
    const grid = document.querySelector('.s-sh-grid');
    const first = order.length ? cards[order[0]].dataset.band : null;
    if (grid) {
        const panels = [...grid.querySelectorAll(':scope > [data-band]')];
        panels.sort((a, b) => Number(b.dataset.band === first) - Number(a.dataset.band === first) || Number(a.dataset.order) - Number(b.dataset.order));
        grid.append(...panels);
    }
}
