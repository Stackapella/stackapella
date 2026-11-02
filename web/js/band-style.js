// The backing band's styles (js/band.js), on their own so a screen that only names or picks a style
// (Sing's settings, the studio, the dialogs) doesn't load the band and its instruments with it: a
// first visit's lesson 1 never needs them (tests/flows/slow_phone.py).

export const STYLES = ['gentle', 'pop', 'swing'];

/** The style a stored value means: the three styles, and the old pad and piano as Gentle. */
export const styleOf = (value) => (STYLES.includes(value) ? value : 'gentle');
