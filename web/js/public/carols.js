// The Christmas carols page (server/resources/views/site/carols.blade.php, Hillel's redesign of 2026-11-10).
// - "I sing": plain buttons (a menu on a phone) that light one part on every carol's card, the others faded, and say
//   so under the songbook's heading. The choice lives only while the page is open: nothing is stored.
// - The countdown: the server's day is a first guess; here it's counted again in the visitor's own day (the card, and
//   its one line on a phone), and it goes once Christmas Day has passed. Nothing about the visitor is kept or sent.
// - The hero's snow pauses while it's off screen.
// The words are the view's (data- attributes).

const book = document.querySelector('[data-songbook]');

if (book) {
    const buttons = [...book.querySelectorAll('[data-sing]')];
    const menu = book.querySelector('[data-sing-menu]');
    const sub = book.querySelector('[data-book-sub]');
    const names = new Map(buttons.map((b) => [b.dataset.sing, b.textContent.trim()]));
    const tiles = [...book.querySelectorAll('[data-part-key]')];

    const sing = (key) => {
        if (key) book.dataset.sing = key;
        else delete book.dataset.sing;
        for (const b of buttons) b.setAttribute('aria-pressed', String(b.dataset.sing === key));
        // The part sung is ringed and the others faded, so it never rests on colour alone.
        for (const t of tiles) {
            t.toggleAttribute('data-sung', Boolean(key) && t.dataset.partKey === key);
            t.toggleAttribute('data-faded', Boolean(key) && t.dataset.partKey !== key);
        }
        if (menu && menu.value !== key) menu.value = key;
        if (sub) sub.textContent = key ? sub.dataset.part.replace(':part', (names.get(key) ?? '').toLowerCase()) : sub.dataset.any;
    };

    for (const b of buttons) b.addEventListener('click', () => sing(b.dataset.sing));
    menu?.addEventListener('change', () => sing(menu.value));
}

const countdown = document.querySelector('[data-countdown]');

if (countdown) {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const christmas = new Date(now.getFullYear(), 11, 25);
    const days = Math.round((christmas - today) / 864e5);

    if (days < 0) {
        countdown.hidden = true;
    } else {
        const labels = countdown.querySelectorAll('[data-count-label]'); // the serious skin's and game mode's (TIME LEFT)
        const count = countdown.querySelector('[data-days]');
        const unit = countdown.querySelector('[data-days-unit]');
        const line = countdown.querySelector('[data-line-days]');
        if (days === 0) {
            for (const label of labels) label.textContent = label.dataset.christmas;
            count.textContent = '25';
            unit.textContent = unit.dataset.christmas;
            if (line) line.textContent = line.dataset.christmas;
        } else {
            for (const label of labels) label.textContent = label.dataset.count;
            count.textContent = String(days);
            unit.textContent = days === 1 ? unit.dataset.one : unit.dataset.many;
            if (line) line.textContent = days === 1 ? line.dataset.one : line.dataset.many.replace(':count', String(days));
        }
    }
}

// The hero's snow rests while it's off screen, so a phone isn't working on what nobody sees.
const snow = document.querySelector('[data-snow]');

if (snow && 'IntersectionObserver' in window) {
    new IntersectionObserver(([entry]) => snow.toggleAttribute('data-paused', !entry.isIntersecting)).observe(snow);
}
