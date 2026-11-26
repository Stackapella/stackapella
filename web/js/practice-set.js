// Practise the set (docs/practice-playlist-plan.md): a choir group's songs played one after another as ONE continuous
// file, so the installed app keeps going with the phone locked (the plan's section 3a: it refuses a new play() while
// locked). Next and Previous are seeks within that file. Pure: unit-tested in tests/practice-set.test.js.

/** The quiet between two songs, in seconds. */
export const GAP_SECONDS = 2.5;

/** Previous goes back to the start of this song when it's this far in, else to the song before (as music players do). */
export const RESTART_SECONDS = 3;

/** Each song's start in the set (its `seconds` long, a gap after each but the last), and the set's length. */
export function timeline(songs) {
    const starts = [];
    let at = 0;
    songs.forEach((song, i) => {
        starts.push(at);
        at += song.seconds + (i < songs.length - 1 ? GAP_SECONDS : 0);
    });
    return { starts, total: at };
}

/** The song playing at `time` (a gap belongs to the song before it): its index, or 0 before the first. */
export function songAt(starts, time) {
    let index = 0;
    for (let i = 0; i < starts.length; i++) if (starts[i] <= time + 0.01) index = i;
    return index;
}

/** Where Next goes from `time`: the next song's start, or null on the last song. */
export function nextStart(starts, time) {
    const i = songAt(starts, time);
    return i + 1 < starts.length ? starts[i + 1] : null;
}

/** Where Previous goes from `time`: this song's start once it's past RESTART_SECONDS in, else the one before (or 0). */
export function previousStart(starts, time) {
    const i = songAt(starts, time);
    if (time - starts[i] > RESTART_SECONDS || i === 0) return starts[i];
    return starts[i - 1];
}

/**
 * Your part's stretch of a song, for Repeat my part: from its first note to the end of its last, in seconds from the
 * song's start (`beatZero`: where beat 0 falls in the song's render). Null when the part has no notes.
 */
export function partSpan(template, partKey, beatZero = 0) {
    const notes = template?.parts?.find((p) => p.key === partKey)?.guide?.notes ?? [];
    if (!notes.length) return null;
    const beatSeconds = 60 / template.tempo;
    const first = Math.min(...notes.map((n) => n.beat));
    const last = Math.max(...notes.map((n) => n.beat + n.length));
    return [beatZero + first * beatSeconds, beatZero + last * beatSeconds];
}

/**
 * The part the set plays louder in each song: the director's assignment, else `picked` where the song has that part,
 * else one with the same name ("Alto" and "Alto"), else null (all parts even).
 */
export function partFor(template, assigned, picked) {
    const parts = template?.parts ?? [];
    if (assigned && parts.some((p) => p.key === assigned)) return assigned;
    if (!picked) return null;
    if (parts.some((p) => p.key === picked.key)) return picked.key;
    const named = picked.name && parts.find((p) => (p.name ?? '').toLowerCase() === picked.name.toLowerCase());
    return named?.key ?? null;
}

/** "1:05" for 65 seconds. */
export function clock(seconds) {
    const s = Math.max(0, Math.floor(seconds || 0));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** A rehearsal or concert still to come (or under way: one with no end counts for 3 hours from its start), soonest first. */
export function upcoming(events, now = Date.now()) {
    const HOURS = 3 * 3600 * 1000;
    return (events ?? [])
        .filter((e) => (e.endsAt ? Date.parse(e.endsAt) : Date.parse(e.startsAt) + HOURS) >= now)
        .sort((a, b) => Date.parse(a.startsAt) - Date.parse(b.startsAt));
}

/**
 * The set's songs, in order: a rehearsal's own (only the group's songs, each once, with the singer's part in each), else
 * all the group's songs (`fromEvent` false: a rehearsal with none picked plays them all). `mine(templateId)`: the
 * singer's assigned part in a group song, or null.
 */
export function setSongs(groupSongs, event, mine = () => null) {
    const bySong = new Map(groupSongs.map((s) => [s.templateId, s]));
    const picked = (event?.songs ?? []).filter((e, i, all) => bySong.has(e.templateId) && all.findIndex((x) => x.templateId === e.templateId) === i);
    if (picked.length) return { fromEvent: true, list: picked.map((e) => ({ song: bySong.get(e.templateId), assigned: e.partKey ?? mine(e.templateId) })) };
    return { fromEvent: false, list: groupSongs.map((s) => ({ song: s, assigned: mine(s.templateId) })) };
}

/** How long a made set is kept on the device (the plan: 14 days, or until the set changes). */
export const KEEP_DAYS = 14;

/** What a set was made from: its sound, the file's format and each song (its version and the part louder). */
export function setSignature(songs, kind, format) {
    return JSON.stringify([format, kind, songs.map((s) => `${s.templateId}@${s.template?.version ?? ''}:${s.partKey ?? ''}`)]);
}

/** A kept set that still serves: made from the same songs, parts and sound, within KEEP_DAYS. */
export function isFresh(record, signature, now = Date.now()) {
    return !!record?.blob && record.sig === signature && now - record.savedAt < KEEP_DAYS * 864e5;
}

/** About how many MB a set of these songs takes at `kbps` (each song's notes, its ring and the gaps), rounded up. */
export function estimateMb(templates, kbps) {
    const seconds = templates.reduce((sum, tp) => {
        const beats = Math.max(0, ...tp.parts.map((p) => Math.max(0, ...(p.guide?.notes ?? []).map((n) => n.beat + n.length))));
        return sum + (beats * 60) / tp.tempo + 1;
    }, Math.max(0, templates.length - 1) * GAP_SECONDS);
    return Math.max(1, Math.ceil((seconds * kbps * 1000) / 8 / 1e6));
}
