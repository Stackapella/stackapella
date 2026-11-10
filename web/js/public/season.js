// The season's touches that follow the visitor's own date, not the server's, so a cached page is still right on the day
// (Hillel, 2026-11-10: "only show the hat on the carols page and in the app and marketing on christmas", then "also add
// eve"). hatDay() is the rule; showHats() puts the Santa hat on the logo (Icons::hat's hidden copy) on those days. The
// carols page wears it all season from the server instead. Nothing about the visitor is kept or sent.

/** Christmas Eve or Christmas Day, by the device's own calendar. */
export const hatDay = (now = new Date()) => now.getMonth() === 11 && (now.getDate() === 24 || now.getDate() === 25);

/** Shows every hidden hat on a hat day. */
export function showHats(now = new Date(), root = document) {
    if (!hatDay(now)) return;
    // The hat is an SVG, which has no `hidden` property: the attribute itself goes.
    for (const hat of root.querySelectorAll('[data-hat-christmas]')) hat.removeAttribute('hidden');
}
