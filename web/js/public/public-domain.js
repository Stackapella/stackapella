// The public domain page's filters (site/public-domain.blade.php; Hillel's design round, 2026-11-19): one chip pressed at a
// time (all, free later, free now, on our site) and a search by song, writer or year as you type, which show only the
// matching songs and writers, hide a year or a part left with none, and say so when nothing matches. Nothing reloads. The Year row (a menu on a phone) jumps to a year, showing only
// the years the chip leaves. The bar's measured height sets where the years pin and where a jump lands (--pd-bar-h). The
// chip is remembered in this browser only (a convenience; the page works without it), and a screen reader hears how many
// people show.

const KEY = 'stackapella.publicDomainChip';

/** The chip kept in this browser, or null (storage blocked: nothing is kept between visits). */
function read() {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

/** Keep the chip for next time; false where storage is blocked (the filter still applies). */
function keep(value) {
    try {
        localStorage.setItem(KEY, value);
        return true;
    } catch {
        return false;
    }
}

/** Whether an item (a person, a song passed down) is in a chip. */
export function inChip(chip, state, site) {
    if (chip === 'all') return true;
    if (chip === 'site') return site === '1';
    if (chip === 'joined') return state === 'joined' || state === 'long';
    return state === chip;
}

/** "3 people shown": the words carry their own plural forms ({0} ...|{1} ...|[2,*] ...). */
export function countWords(words, n) {
    const forms = String(words ?? '').split('|');
    const pick = forms.find((f) => f.startsWith(`{${n}}`)) ?? forms.find((f) => f.startsWith('[2,*]')) ?? '';
    return pick.replace(/^(\{\d+\}|\[\d+,\*\])\s*/, '').replace(':count', String(n));
}

const bar = document.querySelector('[data-pd-filter]');

if (bar) {
    const chips = [...bar.querySelectorAll('[data-filter]')];
    const search = bar.querySelector('[data-pd-search]');
    const shown = bar.querySelector('[data-pd-shown]');
    const jumps = [...bar.querySelectorAll('[data-pd-jump]')];
    const pick = bar.querySelector('[data-pd-pick]');
    const parts = [...document.querySelectorAll('[data-pd-part]')];
    const none = document.querySelector('[data-pd-none]');
    const noneLine = none?.querySelector('[data-words]');
    let chosen = 'all';

    const kept = read();
    if (kept && chips.some((chip) => chip.dataset.filter === kept)) chosen = kept;

    const songlist = document.querySelector('[data-pd-songlist]');
    const songsMore = document.querySelector('[data-pd-songs-more]');
    const tobar = document.querySelector('[data-pd-tobar]');

    // Where the years pin and a jump lands follows the bar as it really is (its Year row, a wrapped search); on a phone,
    // where it doesn't stick, nothing is in the way.
    const measure = () => {
        const sticks = getComputedStyle(bar).position === 'sticky';
        document.documentElement.style.setProperty('--pd-bar-h', `${sticks ? Math.round(bar.getBoundingClientRect().height) : 0}px`);
    };
    measure();
    if ('ResizeObserver' in window) new ResizeObserver(measure).observe(bar);
    addEventListener('resize', measure);

    // The songs list shows its first songs until asked for the rest; a search looks through them all.
    let songsOpen = false;
    if (songlist && songsMore && songlist.children.length > 15) {
        songlist.classList.add('is-short');
        songsMore.hidden = false;
        songsMore.querySelector('button')?.addEventListener('click', () => {
            songsOpen = true;
            songlist.classList.remove('is-short');
            songsMore.hidden = true;
            songlist.querySelector('li:nth-child(16) a')?.focus();
        });
    }

    // On a phone, once the bar has scrolled away, one small pill brings it back.
    if (tobar && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            tobar.hidden = entry.isIntersecting || entry.boundingClientRect.top > 0;
        }).observe(bar);
    }

    // `say`: tell a screen reader how many show (after the visitor acts, never on load).
    function apply(say = false) {
        const q = (search?.value ?? '').trim().toLowerCase();
        let people = 0;
        let any = 0;
        for (const part of parts) {
            let inPart = 0;
            const years = part.matches('[data-pd-year]') ? [part] : [...part.querySelectorAll('[data-pd-year]')];
            for (const year of years) {
                let inYear = 0;
                let peopleInYear = 0;
                for (const item of year.querySelectorAll('[data-pd-person], [data-pd-item]')) {
                    const show = inChip(chosen, item.dataset.pdState, item.dataset.pdSite) && (q === '' || (item.dataset.pdText ?? '').includes(q));
                    item.hidden = !show;
                    if (show) {
                        inYear += 1;
                        if (item.matches('[data-pd-person]')) peopleInYear += 1;
                    }
                }
                if (year !== part) year.hidden = inYear === 0;
                inPart += inYear;
                people += peopleInYear;
            }
            part.hidden = inPart === 0;
            any += inPart;
        }
        if (songlist && songsMore && !songsOpen && songlist.children.length > 15) {
            const searching = q !== '';
            songlist.classList.toggle('is-short', !searching);
            songsMore.hidden = searching;
        }
        // The Year row offers only the years still showing.
        for (const jump of jumps) {
            const target = document.querySelector(jump.getAttribute('href') ?? jump.value);
            jump.hidden = !target || Boolean(target.closest('[hidden]'));
        }
        for (const chip of chips) chip.setAttribute('aria-pressed', String(chip.dataset.filter === chosen));
        if (none) {
            none.hidden = any > 0;
            if (noneLine && any === 0) noneLine.textContent = (noneLine.dataset.words ?? '').replace(':q', search?.value.trim() ?? '');
        }
        if (shown && say) shown.textContent = countWords(shown.dataset.words, people);
    }

    for (const chip of chips) {
        chip.addEventListener('click', () => {
            chosen = chip.dataset.filter;
            keep(chosen);
            apply(true);
        });
    }
    search?.addEventListener('input', () => apply(true));
    pick?.addEventListener('change', () => {
        const target = pick.value ? document.querySelector(pick.value) : null;
        if (target) {
            target.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            history.replaceState(null, '', pick.value);
        }
        pick.value = '';
    });
    none?.querySelector('[data-pd-reset]')?.addEventListener('click', () => {
        if (search) search.value = '';
        chosen = 'all';
        keep(chosen);
        apply(true);
        chips[0]?.focus();
    });
    apply();
}
