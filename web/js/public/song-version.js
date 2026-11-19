// A song's versions on its page and its part pages (site/_song-versions.blade.php; Hillel, 2026-11-18, "One song, US/UK
// switch"): the server opens the page on the version the visitor's country sings (the UK's in the UK), and a link picks
// another. A pick is kept on this device (a convenience, like the tools' settings), so the song's other pages, and the
// next visit, open on it: a page that opened on another version without asking (no ?version=) goes to the kept one at
// once. Nothing is sent. Without the script the links still switch, and the page opens on its country's version.

const KEY = 'stackapella.songVersion.';

function kept(song) {
    try {
        return localStorage.getItem(KEY + song);
    } catch (error) {
        console.info('A kept song version could not be read', error?.message ?? error);
        return null;
    }
}

function keep(song, name) {
    try {
        localStorage.setItem(KEY + song, name);
    } catch (error) {
        console.info('The song version could not be kept', error?.message ?? error);
    }
}

const nav = document.querySelector('[data-song-versions]');
if (nav?.dataset.versionSong) {
    const song = nav.dataset.versionSong;
    const links = [...nav.querySelectorAll('[data-version-pick]')];
    for (const link of links) link.addEventListener('click', () => keep(song, link.dataset.versionPick));
    nav.dataset.ready = ''; // the picks are kept from here on (the flows wait for it)
    // A kept pick, on a page that chose for itself: open the kept version instead (an address that asks keeps its own).
    const pick = kept(song);
    const asked = new URLSearchParams(location.search).has('version');
    const to = !asked && pick && pick !== nav.dataset.version ? links.find((link) => link.dataset.versionPick === pick) : null;
    if (to) location.replace(to.href + location.hash);
}
