# Harmony templates plan

**Status:** Phase 1 is built (2026-10-26); slow practice (100%, 85%, 70%) was built in M3
(2026-10-27, `docs/more-features-plan.md`, feature 10). Phase 3's server side (the public library)
was built in N3 (2026-10-27), behind the `template_library` flag.
There are 8 draft templates, all SATB, waiting on a musician's check (`docs/templates-review.md`).
**Next,** in [roadmap.md](roadmap.md):
- real singers' voices as the official guides (M2, [beta-singers-plan.md](beta-singers-plan.md));
- Sing, the learning UX, and the course (M3, [singing-ux-plan.md](singing-ux-plan.md),
  [vocal-coaching-plan.md](vocal-coaching-plan.md));
- making templates in the app, at launch (M7, [template-creator-plan.md](template-creator-plan.md);
  `docs/backend-plan.md` D1);
- the public library and its search, when the network features panel says it's ready (N3).

**Superseded since this was written:**
- **Guides are no longer only synthesized:** beta singers record the official ones, which play in
  the original key and fall back to the synth.
- **Live pitch feedback** is in `docs/vocal-coaching-plan.md`.
- **The format** below is the first sketch. The real format is the one the 8 templates use,
  summarised in `docs/architecture.md`, and it gets a JSON Schema in `/contract`.
- **New since (2026-10-26):** an optional `chords` field for Chords underneath, and learning paths
  built from the templates (`docs/more-features-plan.md`, features 2 and 3).

**The idea:** a template is a ready-made arrangement split into parts. Each part has a guide the
singer learns from, then replaces with their own recording, until the whole arrangement is their
voice. Practice apps teach parts but don't record you; recording apps record you but leave
beginners guessing what to sing. Templates do both, and a public library lets the community add
more.

This changes one earlier decision. The brief kept public profiles and social features out of
scope; the public library brings creator profiles, ratings and moderation in (Phase 3).

## How it fits what's built

**A template opens as a normal project.** Every part becomes a **slot** with two layers:
- the **guide**: synthesized from notes, or a recording;
- the singer's **take**, once recorded.

Mixing, saving, export and storage stay exactly as they are; a slot just plays its guide, its
take, or both (for comparison).

**The engine already does what learning needs most:** exact sync, recording over other parts, and
latency compensation. It needs four additions:
1. **Loop a section:** every part loops the same frames on the audio clock, so they stay in step.
2. **Listen, then repeat:** play a phrase, then the same length of silence (with the click if
   wanted) for the singer to answer, then the next phrase. All scheduled on the audio clock, never
   with timers.
3. **Synthesized guides:** render a part's notes to audio in the browser (an "oo" vowel voice),
   offline, once per key. Transposing is just rendering again.
4. **Pitch detection,** for the range check: find the sung note from the mic (YIN or
   autocorrelation) in `web/js/dsp.js`, unit-tested like the rest of the maths.

**Phase 1 needs no backend:**
- Official templates are static files in `web/templates/`.
- Guides are synthesized on the device.
- Projects started from a template are saved in IndexedDB like any other.

That means templates can ship to testers before accounts exist.

## The template format

One JSON file per template version, our own format (MusicXML import comes later). **This is the
first sketch;** the format in use (SATB parts, a lyric per note, `voicings`, `pickupBeats`) is in
`docs/architecture.md`:

```jsonc
{
  "id": "amazing-grace",
  "version": 1,
  "title": "Amazing Grace",
  "credits": { "song": "John Newton (words), traditional (tune)", "arrangement": "Stackapella" },
  "description": "...", "difficulty": "beginner", "genre": ["hymn"], "tags": [],
  "tempo": 72, "key": "G", "timeSignature": "3/4",
  "licence": { "song": "public-domain", "arrangement": "original", "recordings": "original", "confirmedAt": "..." },
  "sections": [{ "name": "Verse 1", "startBeat": 0, "endBeat": 48, "phrases": [[0, 12], [12, 24], [24, 36], [36, 48]] }],
  "lyrics": [{ "beat": 0, "text": "A-" }, { "beat": 1, "text": "maz-" }],
  "parts": [
    {
      "key": "melody", "name": "Melody", "range": { "low": 62, "high": 74 },
      "guide": { "type": "notes", "notes": [{ "beat": 0, "length": 1, "pitch": 67, "lyric": "A-" }] }
    },
    {
      "key": "low", "name": "Low harmony", "range": { "low": 55, "high": 67 },
      "guide": { "type": "audio", "file": "guides/low.flac" }
    }
  ],
  "backing": { "drone": true, "click": true },
  "parent": null
}
```

- **Times are in beats,** so a change of tempo or key doesn't touch the data. Pitches are MIDI note
  numbers, so transposing a synthesized guide is adding semitones.
- **A version never changes once published.** A project records the template id and version it
  started from, so old projects keep working after the template is edited.

## The learning flow (Phase 1)

### The templates shelf

A **Templates** shelf sits on the projects page. Each card shows the title, number of parts,
difficulty, key, tempo and length. Opening a card shows its details, and **Start** makes a
project from it.

### The studio in learning mode

- **Each part row has three modes:**
  - **Listen:** this part's guide alone, or with the others (a toggle).
  - **Sing along:** the guide at reduced volume, with a slider for how much, plus the other parts.
  - **On your own:** this part's guide muted, the other parts playing.
- **Sections and phrases** show on the ruler. Tapping one selects it, and two controls act on it:
  - **Loop**
  - **Listen, then repeat**
- **Lyrics** run in a lane under the ruler, with the current syllable highlighted.
- **Record my part:** the existing record flow, aimed at this part's slot. The take replaces the
  guide in the mix, and **Compare** switches back to the guide.
- **Progress:** each part shows guide, recorded, or both. When every part is recorded, the
  arrangement is entirely the singer's own, and export works as for any project.

### Range check and transposing

- **The range check** asks the singer to sing their lowest and highest comfortable notes, with a
  live pitch readout. It then suggests a key (and which part fits them) so each part's `range`
  sits in their voice.
- **Synthesized guides** transpose without any loss.
- **Recorded guides** would need pitch-shifting, which sounds worse the further it goes. In Phase 1
  they're either not transposed or limited to a few semitones, with a note about the quality.

## Creating and sharing (Phases 2 and 3)

### Creating a template (Phase 2, needs accounts and cloud save)

- **From a project:** mark parts as guides, name each part, and set its range. Then add the
  metadata, sections and phrases, and lyrics. Timing the lyrics means tapping along while the
  guide plays.
- **From scratch:** record each guide in creation mode.
- **Preview as a learner** before saving.
- **Editing** makes a new version; projects keep the version they started from.
- **Visibility:**
  - **Private** (the default)
  - **Unlisted:** anyone with the link can use it.
  - **Public** is Phase 3.
- **Inviting a friend to take a specific part** comes with collaboration, on the same invite rules
  as [pricing-plan.md](pricing-plan.md).

### The public library (Phase 3)

These must be in place **before the first public template:**
- **Licensing confirmation when publishing:** the song is original or public domain, and the
  arrangement and recordings are the creator's own. The declaration is stored with the version.
- **A Report button** on every public template (copyright, offensive, other).
- **Admin moderation:** a review queue, unpublish, remove, and ban.
- **A copyright takedown process:** notice and counter-notice, documented in `docs/`.
- **Terms of service** that grant the right to host and show shared templates.
- **Abuse protection:**
  - rate limits on publishing;
  - a new creator's first public templates are held for review.

Then the library itself:
- **Search, filters and sorting:**
  - search;
  - filters: difficulty, number of parts, genre, key, tempo, voice ranges;
  - sorting: popular, newest, top rated.
- **Curation:** a curated **Featured** shelf, including the official templates.
- **Social signals:** ratings or likes, "used by N people", and creator profiles listing their
  public templates.
- **Attribution:**
  - projects from a template credit its creator;
  - exports carry the credit, in the file's metadata and the name.
- **Remix:** copy a public template into a new one that links back to the original (`parent`).

### On the server (from Phase 2)

- **`templates`:** owner, visibility, current version, `parent_id`, and counters (uses, ratings).
- **`template_versions`:** immutable, holding the JSON above and the licensing declaration.
- **`template_guides`:** recorded guides. People's own are in the private bucket, served by
  temporary URL; the official ones (sung by beta singers) are public on the CDN
  (`docs/backend-plan.md` D1).
- **`template_ratings`** and **`template_reports`**, the latter with status, reason and
  resolution.
- **On existing tables:**
  - `projects.template_version_id`;
  - each part gets the template part key it fills, and whether its guide or its take is playing.
- **Moderation** follows ES and iaw's admin pattern: `admin` middleware, a second factor for admins,
  and every action written to an audit log.

## The official library (revised 2026-10-26)

**No licensed music** (Hillel's call): only songs that are public domain everywhere we sell, and
our own originals.

**The rule for "public domain everywhere."** The tune, the words, and any translation used must
all pass both tests:
1. **US:** published in 1930 or earlier (as of 2026; one more year passes each January).
2. **UK and EU:** every writer (composer, lyricist, translator) died before 1956 (life plus 70
   years; one more year passes each January).

**The traps,** checked by the musician and confirmed once with the lawyer:
- **Old popular songs that fail the second test:**
  - *Shine On, Harvest Moon* and *Take Me Out to the Ball Game* (Jack Norworth died 1959);
  - *When Irish Eyes Are Smiling* (George Graff died 1973);
  - *Ain't She Sweet*;
  - *Swanee* (Irving Caesar died 1996).
- **Later additions still protected:**
  - Simon & Garfunkel's *Scarborough Fair* counter-melody;
  - the English words of *Carol of the Bells* (1936);
  - the 1950s verse of *How Can I Keep from Singing*.

  Use the original.
- **Disputed songs,** left out unless the lawyer clears them: *Kumbaya*, *This Little Light of
  Mine*, and *Kookaburra* (1932, enforced in court).
- **Every arrangement is original,** written for Stackapella and never copied from a published
  arrangement or recording. The basis for each song is recorded in the template's `licence`.

**Free and Plus** (`docs/pricing-plan.md`):
- **Four songs are free:** Amazing Grace, Are You Sleeping, Michael Row the Boat Ashore (the free
  course's songs) and Happy Birthday.
- **Everything else is Plus,** with a free song of the month, and new songs every month.

**The launch library** (proposed, about 20 songs):
- **the 8 drafted:**
  - Amazing Grace, Are You Sleeping and Michael Row (free);
  - Silent Night, Auld Lang Syne, Danny Boy, Ode to Joy and Red River Valley;
- **Happy Birthday** (free);
- **the first new batch:**
  - Row, Row, Row Your Boat; Shenandoah; Down to the River to Pray;
  - Swing Low, Sweet Chariot; Sweet Adeline; Let Me Call You Sweetheart;
  - Simple Gifts; Scarborough Fair (traditional); Be Thou My Vision;
  - *Shchedryk* (with the Ukrainian words, or new words of our own); O Come, All Ye Faithful.

**The candidates after that,** about two a month:

| Kind | Songs |
|---|---|
| **Rounds** (the easiest first harmony) | Dona Nobis Pacem, Viva la Musica, Hey Ho Nobody Home, Shalom Chaverim, Make New Friends, London's Burning, Sumer Is Icumen In |
| **Folk** | The Water Is Wide, Wayfaring Stranger, Greensleeves, Loch Lomond, The Parting Glass, Skye Boat Song, Molly Malone, My Bonnie, Clementine, I've Been Working on the Railroad, Home on the Range, Hard Times Come Again No More, Beautiful Dreamer |
| **Spirituals** | Deep River, Steal Away, Wade in the Water, Go Down Moses, Down by the Riverside |
| **Barbershop and early popular songs** | Down by the Old Mill Stream, In the Good Old Summertime, Daisy Bell, Wait Till the Sun Shines Nellie |
| **Hymns** | Abide with Me, How Can I Keep from Singing (original verses), Shall We Gather at the River, It Is Well with My Soul |
| **Christmas** (ready for December) | Hark! The Herald Angels Sing, Joy to the World, O Holy Night, The First Noel, Deck the Halls, God Rest Ye Merry Gentlemen, Good King Wenceslas, Angels We Have Heard on High, Away in a Manger, We Wish You a Merry Christmas, Jingle Bells |
| **Classical and choral** | Mozart's Ave verum corpus, Bach chorales, Tallis's Canon, Brahms's Lullaby |
| **Stackapella originals** | New songs we write and own (Claude drafts, the musician refines): modern-sounding songs with no rights questions |

**Who writes them:** Claude drafts each arrangement as note data, and a musician checks every part
(`docs/templates-review.md`).

**What people may do with them:** share and monetise the harmonies they record, crediting
"Arranged by Stackapella". Re-selling the arrangements themselves (the notes and guides) isn't
allowed (`docs/go-to-market-plan.md`).

## Order of work

### Phase 1: the template player and official templates (browser only, can start now)

1. The template format, a loader, and two official templates to build against.
2. The guide synthesizer (notes to audio, offline), with transposition, plus tests: the right
   pitches at the right beats, compared sample-accurately with a reference render.
3. Engine additions: loop a section, and listen-then-repeat, both on the audio clock. Loopback tests
   prove every part stays aligned across loops.
4. Slots (guide plus take), the three modes, the guide volume, and Compare.
5. The templates shelf, the template details, and starting a project from a template.
6. The lyrics lane, and sections and phrases on the ruler.
7. Pitch detection, the range check and the suggested key, with tests on generated tones.
8. The rest of the official templates.

### Phase 2: user-made templates, private and unlisted (after accounts and cloud save)

1. The server tables for templates, versions and guides.
2. Creating from a project or from scratch, with metadata and timed lyrics.
3. Preview, editing as new versions, and unlisted links.
4. Inviting a friend to a part.

### Phase 3: the public library

1. Licensing declarations, reports, moderation tools, the takedown process, the terms, and
   publishing limits, built and tested first.
2. Then the library itself: search, filters, featured, ratings, use counts, creator profiles,
   attribution in exports, and remixing.

### Later (designed for, not built)

- Live pitch feedback (whether you're on pitch): now planned in `docs/vocal-coaching-plan.md`.
- MusicXML import.
- Licensed songs.
- Comments on templates.

## Open decisions

| # | Decision | Recommendation |
|---|---|---|
| 1 | Does a project started from a template count toward the 3 free projects? | **Decided:** yes, like any project. Preview doesn't count. |
| 2 | Free or Plus: are some official templates Plus? Is publishing free? | **Decided (2026-10-26):** ~~all official templates are free~~ **revised the same day: four official songs are free, the rest Plus, with a free song of the month** (`docs/pricing-plan.md`). Creating and publishing public templates is free. Plus gets unlimited private or unlisted templates (free gets 1), recorded human guides, and private group sharing with parts assigned. See `docs/pricing-plan.md`, Templates. |
| 3 | Who arranges the official templates? | **Decided:** Claude drafts, a musician checks every part. |
| 4 | Official guides: synthesized or sung by real singers? | **Decided:** synthesized, then **revised (2026-10-26):** real singers' voices from the beta, with the synth as the fallback (`docs/beta-singers-plan.md`). |
| 5 | Public profiles and ratings: OK, now that the library needs them? | **Decided (2026-10-26):** yes, with the public gallery (`docs/backend-plan.md` D6). |
| 6 | Who moderates, and how fast? | Decide before Phase 3. A review hold on new creators' first public templates keeps the queue small. |
| 7 | Mobile: does the learning mode need a different layout? | **Decided:** designed in Claude Design and planned in `docs/singing-ux-plan.md`. |

## How we'll know it works

- **Unit tests (headless Chrome, `web/tool/test.sh`):**
  - the synthesizer puts the right pitch on the right beat;
  - transposing moves every note by the right number of semitones;
  - pitch detection finds generated tones to within a few cents;
  - the template loader rejects broken files.
- **Loopback tests:** loops and listen-then-repeat keep all parts exact to the sample. A take
  recorded into a slot lines up with the guide it replaces.
- **By hand:** an official template learned and recorded part by part, on desktop and an iPhone,
  in headphones.
- **From Phase 2, server feature tests:**
  - versions are immutable;
  - private and unlisted access rules hold;
  - publishing needs the licensing declaration;
  - reports and moderation actions work, and are audited.

---

[Back: Decisions](decisions.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Beta singers](beta-singers-plan.md)
