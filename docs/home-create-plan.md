# Home: one way to start a song

**Status:** decided 2026-10-28 (Hillel), for the frontend agent to build. Decisions are logged in
[decisions.md](decisions.md).

## Why

Home had two ways to start something new, and they made different things. "Make your own" in
Learn a harmony opened the song builder, which writes a harmony with notes. "New song" in Your
songs opened an empty studio. A song you wrote also showed in the library under Yours, while its
recordings showed in Your songs, so one song sat in two places. Hillel: "There are two ways to
create a new song ... I think we need to combine the two paths and figure out how it all comes
together."

## The shape

```
Lead card (the course: Start singing, or Continue)

Your songs                                   [+ New song]
  [Amazing Grace · 2 of 4 parts]  [My Lullaby · written by you]  [Untitled · draft]

Song library                                 All | Plus
  [Amazing Grace] [Happy Birthday] [Michael Row] ...  See all
```

1. **One "New song"**: Your songs' first card, blank with a plus (Hillel, 2026-10-29: "go back to the first song being blank and a plus button, instead of the new add song button"); no button in the band's head. It opens a
   sheet with two choices, each a sentence long (no Learn a song: Hillel, "you can click a song to do it"):
   - **Make a harmony**: the song builder (sing it, write it, start from chords, open a file).
   - **Just record**: an empty studio, no notes (today's New song).
   The free plan's song limit is checked on the sheet, as today.
2. **Your songs holds everything of yours**: recordings (projects), songs you've written in the
   builder ("Written by you", opening the song's page), and unfinished builder drafts ("Draft,
   edited 2h ago", with Resume and Discard). Newest first. The library's Yours and Drafts filters
   and the "Unfinished" list under it go.
3. **The library is the catalogue only**: no create card, filters All and Plus, Real voices and
   Free this month tags as now. Its title is "Song library" for returning singers; a first visit
   keeps "Hear a harmony first".
4. **First visit**: Your songs shows just its blank New song card with one line ("Nothing yet. Learn one
   from the library, or start your own."), below the lead card and above the library.

## Done means

- One create entry on Home; no song appears in two bands.
- Flows: the sheet's three choices land in the right place; a written song and a draft show in Your
  songs and not in the library; the limit still stops a fourth free song. Phone and desktop shots
  for Hillel before landing.
- ux-spec and a ux-changes line for the apps (stackapella-31).
