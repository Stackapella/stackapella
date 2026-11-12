// The film's own words in each language (?lang=es; render.py --lang=es): every word the film itself draws, the
// titles, lines, part names, the share card and the end card. English is the key. The words are the site's and the
// app's own where they have them (server/lang/es, web/locales/es.json), the rest by docs/languages/es-glossary.md,
// read by the courses lead. A line is [words, the word that lights]. The singing stays as sung, in English; the
// app's screens come from that language's captures (capture.py --lang=es). A word with no translation stops the
// render: nothing is left in English by accident.

import { Q } from './lib.js';

export const LANG = Q.get('lang') || 'en';
const WORDS = {
    es: {
        Soprano: 'Soprano', Alto: 'Contralto', Tenor: 'Tenor', Bass: 'Bajo',
        You: 'Tú', 'You, again': 'Otra vez tú',
        'a real voice': 'una voz real',
        'Slide up': 'Sube',
        'Harmony,': 'Armonía,', 'made by you.': 'hecha por ti.',
        'Sing together': 'Cantar juntos', 'A link for each part': 'Un enlace para cada voz', 'Link copied.': 'Enlace copiado.',
        Tuner: 'Afinador', Drone: 'Bordón', Metronome: 'Metrónomo', 'Vocal range test': 'Test de extensión vocal',
        'Learn your harmony from a real singer.': ['Aprende tu armonía con una voz real.', 'real.'],
        'Record each part over the others.': ['Graba cada voz sobre las demás.', 'demás.'],
        'Stack your own voices, exactly in time.': ['Superpón tus propias voces, exactamente a tiempo.', 'tiempo.'],
        'The whole song is you.': ['Toda la canción eres tú.', 'tú.'],
        'Free to learn.': ['Aprender es gratis.', 'gratis.'],
        'Or play it as a game.': ['O cántala en modo juego.', 'juego.'],
        'For one voice, or a whole choir.': ['Para una sola voz o para todo un coro.', 'coro.'],
        'Open source on GitHub.': ['Código abierto en GitHub.', 'GitHub.'],
    },
};
if (LANG !== 'en' && !WORDS[LANG]) throw new Error(`no words for "${LANG}": ${Object.keys(WORDS).join(', ')}`);
const word = (s) => { const w = WORDS[LANG][s]; if (w == null) throw new Error(`no ${LANG} words for "${s}" (js/words.js)`); return w; };
/** `s` in the film's language. */
export const tr = (s) => (LANG === 'en' ? s : [word(s)].flat()[0]);
/** A line and the word of it that lights, in the film's language. */
export const LINE_T = (s, accent) => (LANG === 'en' ? [s, accent] : word(s));
/** The site's address for the language: stackapella.com, stackapella.com/es. */
export const SITE = LANG === 'en' ? 'stackapella.com' : `stackapella.com/${LANG}`;
