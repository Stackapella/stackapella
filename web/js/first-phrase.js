// A guest's taste of a library song: its first line (templates.js re-exports it; the mock API cuts with it too, so it
// imports nothing, and the public pages that load the mock reach no more than this).
/**
 * A guest's taste of a library song (Hillel, 2026-11-15: "Sing the first phrase, then ask"): the song cut to its first
 * phrase, as the server's `grant: "phrase"` sends one (backend, agreed). Every beat-keyed thing ends where the phrase
 * does (a note running past it is clipped); a part with no note in it is left out (a round's later voice), with the
 * order and voicings that name it; the phrase ends at the last kept note's end if that's sooner. A first phrase the
 * tune doesn't sing in runs on to the end of the first one it does. Null when the tune never sings.
 */
export function firstPhrase(template) {
    const melody = (template.parts.find((p) => p.melody) ?? template.parts[0])?.guide.notes ?? [];
    // The first phrase the tune sings in (an intro the others sing alone runs on into it: El Cant dels Ocells).
    const sung = (template.sections ?? []).flatMap((s) => s.phrases).find(([start, end]) => melody.some((n) => n.beat >= start - 1e-6 && n.beat < end - 1e-6));
    if (!sung) return null;
    const phrase = [template.sections[0].phrases[0]?.[0] ?? sung[0], sung[1]];
    const cut = (notes, end) => notes.filter((n) => n.beat < end - 1e-6).map((n) => (n.beat + n.length > end ? { ...n, length: end - n.beat } : n));
    const parts = template.parts.map((part) => ({ ...part, guide: { ...part.guide, notes: cut(part.guide.notes, phrase[1]) } })).filter((part) => part.guide.notes.length);
    const end = Math.min(phrase[1], Math.max(...parts.flatMap((part) => part.guide.notes.map((n) => n.beat + n.length))));
    const kept = new Set(parts.map((part) => part.key));
    const { builder: _builder, meters, ...rest } = template;
    const changes = meters?.filter((m) => m.beat < end - 1e-6);
    return {
        ...(changes?.length ? { meters: changes } : {}),
        ...rest,
        parts,
        // One phrase: the intro and the tune's first line together, in the first section.
        sections: [{ ...template.sections[0], endBeat: end, phrases: [[phrase[0], end]] }],
        ...(template.chords ? { chords: template.chords.filter((c) => c.beat < end - 1e-6) } : {}),
        ...(template.order ? { order: template.order.filter((key) => kept.has(key)) } : {}),
        ...(template.voicings ? { voicings: Object.fromEntries(Object.entries(template.voicings).filter(([, list]) => list.every((key) => kept.has(key)))) } : {}),
    };
}
