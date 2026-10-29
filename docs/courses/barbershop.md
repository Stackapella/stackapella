# Barbershop and tags: the course outline

Status: built by courses-skills (2026-10-29), all four units, 16 lessons, coming soon until the
lead's review; outline reviewed by a barbershop coach and voice teacher and a game designer
(courses-harmony). Section 10 says what the build changed and what's still to come (sections 1 to 4 are the outline as
planned: 14 lessons, Sweet Adeline and an audition; the build has 16, Old Mill Stream and no audition).
Section 11 is the lead's review. It follows the
[build plan](../plus-courses-build-plan.md) (section 6a) and reuses Counterpoint's partner lines
(`with`), Hear it together, per-note `cents`, `under` and `harmony.js`
([counterpoint.md](counterpoint.md)). Facts come from the checked lists in
[plus-courses-content.md](../plus-courses-content.md) and the course review (facts 24 to 26). The
arranger checks every voicing and target before it lands. Section 9 lists what the reviews changed.

Back: [Plus courses](../plus-courses-plan.md) · [Build plan](../plus-courses-build-plan.md)

---

## 1. The course in short

- **The skill:** four parts tuned so closely the chord rings: a straight tone, a low third, a lower
  seventh, and each barbershop part's job.
- **Shape:** 4 units, 14 lessons, 2 to 4 minutes each, about 45 minutes.
- **The arc:** make one note ring → build a chord from the bottom (octave, fifth, third, seventh) →
  sing each of the four parts → tags, a swipe and a post, ending with two parts both you.
- **Catalogue:** `id: barbershop`, prefix `bb`.
- **Lesson 1 is free:** 4 seconds of a finished, ringing chord; then the same chord with a hole in
  it, "This note is yours"; you hold the third with its cents moving within 40 seconds; then the
  scored hold; then **You vs the piano**: your take with the chord, then the chord moved so your note
  sits where the piano's would be, and it beats. Ends: "In unit 4, two of these voices are you."
- **Needs headphones.** On a speaker the mic hears the chord and can latch onto a guide, so every
  ring step says so before you start; on a speaker the chord plays quieter and the result says the
  measure is less sure.
- **Parts by their barbershop names:** the lead sings the tune, the tenor floats above it, the bass
  sings roots and fifths, the baritone fills the chord, often crossing the lead. The whole voicing
  moves to the learner, so their line sits within +5 and -9 and the guides sit above or below as the
  part needs. A bass part is never moved up an octave alone (it would turn the chord upside down).

## 2. What the app measures, and how the stars are honest

| Measure | What it is | Where |
|---|---|---|
| **How far from ringing** | A held chord note's centre over the last 1.5 seconds (skipping the first 300 ms) against its pure target, in cents, with its direction: "6 cents high: let it fall" | new scorer `ring`, `harmony.js` |
| **The lock** | Your note within 5 cents of its target for half a second: it shows as locked, and goes off past 7, so it never flickers. "Locked" means your note is tuned to ring with the guides, said exactly so | `exercise.js` (with the frontend), game-skin colour, the cents said in both skins |
| **Pitch** | Melody and part lines on pitch at ±20 cents | `scoring.js` (precise) |
| **Straight** | How straight a held note stayed | `score: "straight"` (exists) |
| **Holds** | Each held note judged on its own | `score: "holds"` (exists) |

**The ring stars:** 3 at 6 cents or closer, 2 at 10, 1 at 15 when the note sits on the right side of
the piano's pitch (so the piano's third never earns ring stars). Lesson 1 is generous: 2 stars at 12.
The seventh's band is wider: 2 stars from -18 down (quartets land it anywhere from about -15 to -31;
the target is -31: "low, lower than you think").

**The targets**, always worded "against the chord's root as the guides sing it", never "against the
piano": the third -14 cents (5:4), the fifth where the piano puts it (+2 is below what any voice can
aim for), the seventh -31 (7:4). The guides' roots stay where the piano puts them, so nothing drifts
over a progression.

**The lead is the reference.** When you sing lead, your notes are at 0 cents and scored on pitch only,
never on ring; the guides tune around you. Ring applies only to held chord notes of a second or more.
In the post, your held note stays fixed and each guide chord is built pure around it.

**Not measured, so self-checked, never starred:** vowels and tone (the guide has no vowels), and the
overtones. Instead of "Did you hear a note nobody sang?" (everyone would say yes), a no-mic Listen
and choose plays a locked and a beating chord: "Which one beats?"

**Help, offered after two missed tries, never forced:** **Hear my note** (the pure target alone for 2
seconds, then the chord); **Sing with my guide** (a guide on your note, which fades out); **Shorter
hold** (2 seconds). A take that crept up gets "You crept up 12 cents over the hold. Softer and
lighter usually holds it down." For a learner who can't hear the lock, a beat ladder: Listen and
choose at 30, then 20, then 14 cents, stopping where they can tell ("You can hear 20 cents now").

## 3. Ranges, voicings and the demo

- Every learner line within +5 and -9; each written line's span checked; a bass line's leaps kept
  within 14 semitones.
- **The ringing demo:** the guide voices sing straight (`vibrato: { cents: 0 }` on every note), on a
  bright vowel tone with harmonics up to at least the 7th, at equal levels. The voicing: do -16, so
  -9, do' -4, with the third (mi, 0) on top, the open voicing that's easiest to hear. 4 seconds with
  the piano's third, 4 seconds pure, then once as one held chord with the third gliding from 0 to -14
  over 3 seconds, so the beats slow and stop. The seventh is demonstrated the same way.
- Lines from Sweet Adeline (1903) and Let Me Call You Sweetheart (1910) come from the app's own
  templates: their melodies from the 1903 and 1910 printings, their parts original to Stackapella
  (never a published barbershop arrangement, and never the well-known Sweet Adeline echoes).

## 4. The units

### Unit 1: Make it ring

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **bb-1-ring: A chord that rings** (free) | Tune the third until the chord rings | Hear 4 seconds of a ringing chord; the chord with a hole: "This note is yours"; Try, unscored: hold mi with its cents shown; Do: three voices hold do, so and do'; you hold mi (target -14), straight, 4 seconds; **You vs the piano** (your take with the chord, then with the chord moved 14 cents); the fact: barbershop harmony grew from Black American quartet singing of the late 1800s | Ring, generously |
| **bb-1-straight: Straight, not squeezed** | A light, straight held note | Why ("Straight, not squeezed: medium-soft. If your throat tightens, let a little wave back in"); Hear straight and wavy against a chord; Do: hold the chord's notes straight over the other three; then a self-check on your own takes: one on "oo", one on "ah", "Which one rang more for you?" | Straight |
| **bb-1-out: Who's out?** | Hear a voice out of tune | Listen and choose, 6 rounds: a chord with one voice 20 cents off, tap the voice; then "Which one beats?" (a locked and a beating chord) | The choices |
| **bb-1-challenge: Any seat** | One chord, your choice of note | The root, the fifth (the easy seat: where the piano puts it), or the third: the other three sing theirs; lock yours | Ring |

### Unit 2: Build the chord

From the bottom up: the easiest lock first.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **bb-2-octave: Octave and fifth** | The easiest locks | Do: the octave over a root, then the fifth, where the piano puts it | Ring |
| **bb-2-hunt: The hunt** | Find the third's lock yourself | Start on the piano's third and glide down slowly until it rings (the lead's `wave` with a `path`); the result says where you stopped | Ring, where you stopped |
| **bb-2-seventh: The barbershop seventh** | The seventh, low | Why (the dominant seventh is "the barbershop seventh"; "low, lower than you think"); Hear it beat, then lock, the glide again; Do: fa over so, ti and re | Ring, the seventh's band |
| **bb-2-challenge: One do, three colours** | The same note, retuned for each chord | Over I, VI7, II7 and V7, the guides' roots where the piano puts them: do (0), then do# (-14, the third of VI7), then do (-31, the seventh of II7), then ti (-14, the third of V7). The Why says it before you sing: "Your do is a different pitch in each chord. Hold it where it was and the seventh sounds sharp: let it drop." The result: "the same do, 31 cents lower as a seventh". Then and now: lesson 1's cents beside today's | Ring across the four |

### Unit 3: The four parts

Each lesson opens with **The hole**: the quartet without that part, then with it. Four seats fill as
you go ("Parts sung: lead, bass" in the serious skin).

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **bb-3-lead: The lead** | The tune, in the second voice from the top | Why (the lead holds the key; the others tune to it); Do: a line of Sweet Adeline's lead with tenor, baritone and bass around you | Pitch |
| **bb-3-bass: The bass** | Roots and fifths, the floor | Why ("don't push down for depth"; chin level); Do: the same line's bass, the whole voicing moved to your range | Pitch, and ring on held chords |
| **bb-3-tenor: The tenor** | Above the lead, light | Why ("light and soft, lighter than your lead"; balance as a self-check: bass and lead strongest, tenor softest); Do: the same line's tenor | Pitch, and ring on held chords |
| **bb-3-bari: The baritone** | The note the chord still needs | Why (the part many find hardest to hear on its own; it crosses the lead); Do: the same line's baritone | Pitch, and ring on held chords |
| **bb-3-challenge: Your seat** | Any part, a whole phrase | A 20-second audition of each part, with "Your voice sits as a..." from your range (the choice stays yours); Let Me Call You Sweetheart's first line in your part | Pitch, and ring on the phrase-end chord |

### Unit 4: Tags

A tag is the end of a song, sung again for the joy of the chord. Ours are original; the famous tags
and the societies' collections are never used or imitated.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **bb-4-swipe: The swipe** | Chords changing under one held word | Do: hold the word while your note moves with the chord | Pitch, ring on each chord |
| **bb-4-post: The post** | Hold one note while the chords move under it | Do: your note fixed; each guide chord built pure around it | Holds and straight |
| **bb-4-challenge: Two of you** | An original tag, two parts both you | Take 1: the lead (your notes the reference); take 2: the tenor or bass (picked from your range) over your own lead (`under`), the other two guides tuned to your take 1. Hear it together; a share card with the last 10 seconds ("2 of 4 voices: me. 3 of 4 chords within 8 cents") beside lesson 1's single note | Pitch and ring on take 2's held chords |

Later: all four parts of the tag yours, once Plus courses can record in Sing; bonus original tags to
replay.

## 5. Fun

- **The lock** in lesson 1, and **You vs the piano**, built only from what you sang.
- **Hear it together** after every take.
- **Lock streak** across the progression and the post (game skin; plain: "3 of 4 chords within 5
  cents"), and **Closest lock**, a personal best ("2 cents").
- **"You just did that"**: your first ringing third, your first barbershop seventh, your first tag,
  two parts of you.
- Facts (checked): barbershop singers tune the major third about 14 cents lower than a piano does;
  when a chord rings, the overtones can sometimes sound like an extra voice; the lead sings the tune,
  the tenor floats above; barbershop harmony has roots in Black American quartet singing of the late
  1800s; the barbershop seventh is tuned close to 4:5:6:7.

## 6. Safe singing

- "Straight, not squeezed: medium-soft. If your throat tightens, let a little wave back in", on
  bb-1-straight and every ring lesson.
- Ring lessons cap at about 6 holds of 4 to 6 seconds.
- The tenor light and soft; the bass never presses down, chin level.
- The shared stop-if-it-hurts lines; headphones on ring steps.

## 7. What it needs built

- `ring`: a scorer (in `lesson-scores.js`) and its result line, as section 2.
- The lock on the lane (with the frontend): within 5 cents for half a second, off past 7; game-skin
  colour, cents in both skins.
- Guides tuned to the learner's own take (bb-4-challenge): a step's partner pitches set from the
  measured pitch of an earlier step's take.
- The lead's `wave` with a `path` for the hunt; the lead's `selfcheck` for the "oo" and "ah" takes.
- The guide's tone: straight, bright, harmonics to the 7th (checked with the frontend's synth).
- The voicings, progression, swipe, post and tags: original, checked by the arranger.

## 9. What the reviews changed

From the **barbershop coach and voice teacher**:
- The guide can't sing vowels: the vowel lesson became a self-check on your own two takes.
- ±20 cents can't judge ringing: a `ring` measure with its own bands, on the centre of the hold,
  never rewarding the piano's third; a lock that can't flicker.
- The progression can't hold one note through I, VI7, II7, V7: a moving line instead (do, do#, do,
  ti), the guides' roots fixed, every target "against the chord's root as the guides sing it".
- The lead is the reference: never scored on ring; the guides tune to it; the post holds still and
  the chords tune around it.
- The demo: straight guides, harmonics to the 7th, an open voicing with the third on top, and a
  3-second glide where the beats slow and stop.
- The whole voicing moves to the learner; never a bass alone up an octave.
- The tenor is "light and soft", not head voice; unit 2 builds from the octave up; the fifth is "where
  the piano puts it"; unit 3 ends on the baritone; lesson 1 gained its unscored Try.
- Honest claims: "locked" means tuned to ring with the guides; the extra voice "sometimes"; no leading
  overtone question.
- Safety: "straight, not squeezed", a cap of about 6 holds, headphones on ring steps.
- Public domain: every part original; no famous tags; the society collections never used.

From the **game-design review**:
- Units 1 and 2 were one activity seven times: the vowels merged into straight; the fifth folded into
  the challenge's easy seat; the third became the hunt (a glide); Who's out? added.
- Lesson 1 hooks sooner: a ringing chord, the hole, a live Try within 40 seconds, then You vs the
  piano, and an ending that sells unit 4.
- The lock honest and audible; direction always said; headphones up front.
- A help ladder: Hear my note, Sing with my guide, Shorter hold, the creeping-sharp line, a beat
  ladder.
- The ending tunes to yourself: take 2's guides tune to take 1, starred on ring, shareable.
- Unit 3 feels like joining a quartet: The hole, the four seats, an audition, ring on phrase ends.
- Fair stars with numbers; the seventh's wider band; pitch and ring said as different measures.
- Lock streak and Closest lock, game-skin only with plain fallbacks; then and now at unit 2's end.

## 10. As built (courses-skills, 2026-10-29)

Every voicing, line and target is the arranger's, checked before it landed.

- **The ring measure** is `web/js/ring.js` (score `ring`), with tests: a held note's centre over its
  last 1.5 seconds against its pure target, the bands of section 2, never crediting the piano's
  third; its result says how many cents and which way ("6 cents high: let it fall"). `partring`
  stars a part half on pitch (within 20 cents) and half on its held chords ringing; a note with
  `ring: false` (a diminished seventh, which has no pure tuning) is pitch only.
- **Unit 1** as section 4, with You vs the piano heard as listening rounds ("Which one rings?",
  the pure third against the piano's) rather than your own take moved, and Who's out? tuned 20
  cents from each voice's ring-tuned pitch.
- **Unit 2** as section 4: the hunt is a held mi whose Hear glides down to the ring (a `bend`); the
  progression's I chord has the bari on mi and the lead on so (no parallel fifths into VI7), four
  beats a chord so the seventh has time to settle.
- **Unit 3** uses the opening of **Down by the Old Mill Stream**'s chorus (1910) in true barbershop
  voicing (the arranger's), not Sweet Adeline's library parts, which are a choir voicing with the
  tune on top. Each lesson opens with the hole, hears the bass's B-flat first, and moves the whole
  voicing to the learner. The lead is starred on pitch only (precise); the others on `partring`,
  ringing "old" and "stream", never "mill". The challenge is Who's missing? (four rounds), then the
  baritone with no try first.
- **Unit 4:** the arranger's original swipe ("Home", the bari's chromatic line under a held lead), post
  (four chords tuned pure around your held note; each breath mid-chord so every hold crosses a
  change) and tag ("Sing just one more song"): take 1 the lead, take 2 the tenor over your own lead
  (`under`), ring on the final chord.
- **Not built yet** (after launch, or with the frontend and the lead): the lock shown on the lane;
  the help ladder (Hear my note, Sing with my guide, Shorter hold, the beat ladder); guides tuned to
  your own take 1 (they stay at their written pure pitches); the 20-second audition per part; Lock
  streak and Closest lock; the share card; then and now at unit 2's end.

## 11. The lead's review (2026-10-29)

- **Every chord member pure, guides included:** a guide's third at -14 and seventh at -31 wherever a
  chord should ring (the seventh's ti; the progression's I, VI7, II7 and V7; the swipe's thirds and
  seventh). Roots and fifths stay at piano pitch. The swipe's E-flat minor 6 is pitch only.
- **The ring measure can't be gamed:** an unsung ringable note is a miss; a wave or a glide (the
  middle half of the window spreading past about ±10 cents) is capped at 40 ("A wave can't ring");
  the third's close band is one-sided (close only at least halfway down from the piano's third);
  through a speaker a ring step scores pitch only, saying "Ringing needs headphones".
- **Honest words:** the lead tunes "stream" low as its seventh; solfège, never fixed note names; the
  tenor in Two of you rings against the guides; the post against its own note; lesson 1's generous
  floor said; the catalogue line promises original tags; a course safety note.
- **Who's out?** is starred on its rounds; the listening rounds' answers vary and gained rounds.
- **Still open** (after launch): the hunt as a measured glide (a `wave` path), unit 2's third kind
  of activity, and a challenge of its own for unit 3 (it repeats the baritone).
- **"Stream"** is a complete F7 in the lessons, the tenor on C, its fifth, at +2 (the arranger's
  call; the library song keeps its doubled root); the tenor's lesson sits a semitone lower to keep
  the seat within +5.

## Sources

Checked in the content review (2026-10-29); every fact a lesson states comes from these.

- Barbershop's African American roots, "cracking up a chord" and the swipe (Abbott, 1992): https://www.jstor.org/stable/3051597 ; https://barbershop.org/about/history-of-barbershop/roots-of-barbershop-harmony ; https://timeline.carnegiehall.org/stories/african-americans-and-barbershop-harmony
- James Weldon Johnson's memory of every barber shop's quartet and the cries of "Hold it! Hold it!" (preface to The Book of American Negro Spirituals, 1925, pages 35 and 36): https://archive.org/details/bookofamericanne0000john
- Louis Armstrong's boyhood street quartet: https://timeline.carnegiehall.org/stories/african-americans-and-barbershop-harmony ; https://www.newworldencyclopedia.org/entry/Barbershop_music
- The fifth voice (expanded sound), and arrangers' aim of barbershop sevenths through 35 to 60 percent of a song's length (measured in time, after Averill): https://en.wikipedia.org/wiki/Barbershop_music ; https://en.wikipedia.org/wiki/Barbershop_quartet ; https://www.barbershop.org/intro-to-barbershop-learning-the-language-of-barbershop
- Pure tuning: the third 14 cents under the piano's, the harmonic seventh 31: https://en.wikipedia.org/wiki/Major_third ; https://en.wikipedia.org/wiki/Harmonic_seventh
- Tags, swipes and posts: https://en.wikipedia.org/wiki/Tag_(barbershop_music)
- Down by the Old Mill Stream (Tell Taylor, 1908, Findlay, Ohio; about four million copies): https://en.wikipedia.org/wiki/Down_by_the_Old_Mill_Stream ; https://en.wikipedia.org/wiki/Tell_Taylor ; https://imslp.org/wiki/Down_by_the_Old_Mill_Stream_(Taylor,_Tell)
- The Barbershop Harmony Society's first songfest, Tulsa, April 11, 1938: https://www.okhistory.org/publications/enc/entry?entry=SP007
