// Song pieces in lessons (docs/song-snippets-plan.md, phase 1): a lesson's last step may be a song piece, a line of a real
// song that sings the lesson's skill (`songPiece: true` on a play step whose exercise is a `song`). It never sets the
// lesson's stars (lesson-steps.js lastScoredAt passes over it, for stars and Sharpen up alike), is kept out of the score
// history, is always relaxed, and is skipped quietly when its song can't load. Behind auth.config's `songPieces` (the
// song_pieces switch): off, the courses are read without them. Pure, no imports.

/** Whether a step is a song piece. */
export const isSongPiece = (step) => !!step?.songPiece;

/** The course with its song pieces, or without them while the switch is off (the same object when nothing changes). */
export function songPieces(course, on) {
    if (on || !course?.units) return course;
    let changed = false;
    const units = course.units.map((unit) => {
        let unitChanged = false;
        const lessons = (unit.lessons ?? []).map((lesson) => {
            if (!(lesson.steps ?? []).some(isSongPiece)) return lesson;
            unitChanged = true;
            return { ...lesson, steps: lesson.steps.filter((step) => !isSongPiece(step)) };
        });
        if (!unitChanged) return unit;
        changed = true;
        return { ...unit, lessons };
    });
    return changed ? { ...course, units } : course;
}
