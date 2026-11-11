// /songs's A to Z (server/resources/views/public/songs.blade.php; Hillel's "Songbook page rebuild", 2026-11-11): the
// collection chips and the title search narrow the list in place, without a reload; each letter with nothing left hides,
// and the count says how many show. The hero's search sends its words here and brings the list into view. The list
// is plain links without this script (the server narrows it for ?q=), so search engines and everyone else read it all.
// Tested in tests/flows/songbook.py.

const index = document.querySelector('[data-songbook-index]');

if (index) {
    const items = [...index.querySelectorAll('li[data-in]')];
    const letters = [...index.querySelectorAll('[data-letter]')];
    const chips = [...index.querySelectorAll('[data-filter]')];
    const search = index.querySelector('[data-index-search]');
    const shown = index.querySelector('[data-shown]');
    const none = index.querySelector('[data-none]');
    let filter = '';

    const apply = () => {
        const words = (search?.value ?? '').trim().toLowerCase();
        let count = 0;
        for (const item of items) {
            const fits = (filter === '' || item.dataset.in.split(' ').includes(filter)) && (words === '' || item.dataset.title.includes(words));
            item.hidden = !fits;
            if (fits) count += 1;
        }
        for (const letter of letters) letter.hidden = !letter.querySelector('li:not([hidden])');
        if (shown) shown.textContent = (count === 1 ? shown.dataset.one : shown.dataset.many).replace(':count', String(count));
        if (none) none.hidden = count > 0;
    };

    for (const chip of chips) {
        chip.addEventListener('click', () => {
            filter = chip.dataset.filter;
            for (const other of chips) other.setAttribute('aria-pressed', other === chip ? 'true' : 'false');
            apply();
        });
    }
    search?.addEventListener('input', apply);

    // The hero's search: its words go to the list's search, and the list comes into view. A narrow phone gets its short
    // placeholder, so the example isn't cut mid-word.
    const hero = document.querySelector('[data-songbook-search]');
    const heroInput = hero?.querySelector('input');
    if (heroInput?.dataset.short && matchMedia('(max-width: 399px)').matches) heroInput.placeholder = heroInput.dataset.short;
    hero?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (search) search.value = hero.querySelector('input')?.value ?? '';
        apply();
        index.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    });
    hero?.querySelector('input')?.addEventListener('input', (event) => {
        if (search) search.value = event.target.value;
        apply();
    });

    apply();
}
