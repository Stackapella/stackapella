# Improvising a harmony: the course outline

Status: built, all 4 units and 16 lessons (courses-harmony, 2026-10-29), staff only and coming soon
until the lead's review passes; the outline was reviewed by a voice teacher and a game designer. It follows the [build plan](../plus-courses-build-plan.md) (section 6a) and
reuses Counterpoint's partner lines, `choices`, Hear it together and `harmony.js`
([counterpoint.md](counterpoint.md)). Facts come from the checked list in
[plus-courses-content.md](../plus-courses-content.md), "Improvising a harmony". Section 9 lists what
the reviews changed.

Back: [Plus courses](../plus-courses-plan.md) · [Build plan](../plus-courses-build-plan.md)

---

## 1. The course in short

- **The skill:** hearing a tune and singing a second part to it on the spot, above or below, without
  copying the tune.
- **The rule it teaches:** sing **the next chord tone up** (or down). Over most notes that's a third;
  over so it's a fourth, because the third above so (ti) rubs against the chord; over a passing
  note of the tune, a third above it too (the arranger's check). That's the real folk and bluegrass
  tenor rule, and it leads straight to "any chord tone".
- **Shape:** 4 units, 16 lessons, 2 to 4 minutes each, about 55 minutes.
- **The arc, by the lights:** every harmony note lit → lights fading → you choose among lit chord
  tones → lights only where the chord changes → no lights, on a tune new to the course.
- **Catalogue:** `id: improvising-harmony`, prefix `imp` (the lead's catalogue entry).
- **Lesson 1 is free:** Michael, Row the Boat Ashore: you sing the tune once, then a harmony above it
  with every note lit, then Hear it together plays you in harmony with the tune.
- **Needs headphones.** On a speaker the tune rests while you sing (the mic would hear it), so the
  course says "Best with headphones" before lesson 1's take, and copied and pulled are never reported
  on a speaker.

## 2. What the app measures (as built)

| Measure | What it is | Where |
|---|---|---|
| **Pitch** | Percent of notes on pitch, for written harmony lines | `scoring.js` |
| **Lit** | With `choices`, the percent of notes landing on a lit pitch. The choices at each note are the chord's tones in the learner's range, never a pitch class the tune sounds during the note, and over a passing tune note nothing that rubs with it (a second, tritone or seventh) | `choices`, `harmony.js`, the generator |
| **Copied** | Singing the tune's own note scores as off pitch (it's never lit) and is said as **pulled onto another voice's note** in the result | `harmony.js` `pulls` |
| **Close** | `rules: "close"`: three stars need four moves in five to be a step or less, with some real moving (not one held note) | `species` |
| **Against** | `rules: "against"`: three stars need half the moving notes to go the other way from the tune | `species` |

Unit 4's "lights off" lessons are this same `choices` measure with `lights: "off"`: nothing drawn, every
note still scored on landing in the chord. Every result says in plain words what was measured, so it
reads in the serious skin with stars and XP hidden.

**Not built yet** (section 8): a By ear count on the course map, Hint lights back for one take, both
takes' scores side by side at the finale, and then and now.

## 3. Ranges and keys

Every line the learner sings stays within +5 and -9 of the comfortable note. The tune sounds wherever
it needs to, about 6 dB under the guide's usual level.

| Tune | Its span (do = 0) | Its do | The learner sings |
|---|---|---|---|
| Michael, Row (a part above) | 0 to +9; the harmony +4 to +12 | -7 | -3 to +5 |
| Michael, Row (a part below) | the harmony -8 to 0 | +3 | -5 to +3 |
| Home on the Range (chorus, above) | checked when transcribed | the harmony's top at +5 | |
| Shall We Gather (below, then above) | about an octave | the learner keeps the same pitches: the tune drops an octave for the chorus ("same notes, now the tune is under you") | checked when built |
| Down to the River to Pray, Red River Valley | about an octave | about -2, only chord tones inside the window lit | |
| The high tenor tune (imp-4-high) | | the tune's top at about +1, so "above" is real singing | |

Every tune is transcribed as steps when built and checked against its first prints (Michael, Row:
the 1867 Slave Songs of the United States; Shall We Gather: Lowry, 1864).

## 4. The units

### Unit 1: A part above

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **imp-1-third: A part above** (free) | Sing the next chord tone above a tune | Why ("The simplest harmony: the tune's next chord tone up. Mostly a third, a fourth over so"; "Best with headphones"); Hear one line of tune and harmony together (8 seconds); Try: hold the harmony on the long notes ("row", "shore", "-lu-") while the tune moves, so the chord rings in the first minute; Do: sing the tune once (you must own it before harmonising it), then the whole first verse a part above, every note lit: mi so do' so do' do' do' / so do' do' do' (a fourth on every so); Hear it together. Ends: "You sang parallel thirds, with a fourth where the third would rub", and 5 seconds of a three-part harmony made by ear (the course's last lesson) | Pitch, generously |
| **imp-1-switch: Major and minor thirds** | Thirds change size to stay in the key | Why (a third above switches between major and minor to stay in the key); Hear it over I, IV and V chords; Listen and choose, 4 rounds: which third fits?; Do: do re mi fa so up the scale, a third above each, over its chords | Pitch |
| **imp-1-fourth: Third or fourth?** | Hear why so takes a fourth | Why (over so, the third above is ti, a seventh against do: it rubs); Listen and choose: which one rubs?; Do: Michael, Row's first line with `choices` on each so (ti and do' both light, do' scored as right, ti said as "rubs") | Pitch, three stars with every so on do' |
| **imp-1-ear: Harmony or the tune?** | Hear a harmony as a harmony, then keep it as the lights fade | Listen and choose, 4 rounds: "Harmony or the tune?", "Which one copied?"; Do: Michael, Row a part above, the lights fading over the second half | Pitch, and By ear said |
| **imp-1-challenge: A new tune** | A part above a tune you know less | Home on the Range's chorus (1870s), a part above, lit at the start of each line only | Pitch, three stars with copied 1 or fewer |

### Unit 2: Below, and holding on

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **imp-2-below: A part below** | The same idea under the tune | Why (a third above, an octave down, is a sixth below; under so, do, not ti); Hear; Do: Michael, Row a part below (its do at +3) | Pitch |
| **imp-2-hold: Hold, then step off** | A pedal, and stepping off a clash | Why (the rescue move: "Lost? Hold your note until it rubs, then step"; a rub usually steps, most often down: over V, do goes to ti); Do: hold do through Michael's second line in 4-beat holds with breaths; on the V7 ("hallelujah") ti lights, then back to do | Holds and the step, each hold on its own |
| **imp-2-echo: Answer in harmony** | Harmonise a line you've just heard | The guide sings a line; on its repeat, you sing a part above it, lit; four lines | Lit, three stars with copied 1 or fewer |
| **imp-2-challenge: Under and over** | Both | Steal Away's refrain (the spiritual): once with your part below the tune, then once above it | Pitch |

### Unit 3: Any chord tone

You choose. Every chord tone lights except the tune's own, and the result says what you made.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **imp-3-chord: Sing the chord** | Know the three notes of each chord | Why (a chord has three notes, and any of them is a harmony); Do: do mi so up and down over each chord of Michael, Row; then one long note a chord, any chord tone lit | Lit, three stars with copied 0 |
| **imp-3-two-ways: Close, then against** | Small moves, then contrary motion | Take 1: stay close (the result counts your steps: "a step or less 7 of 9"); take 2: move against the tune (contrary moves counted); the result compares both | Take 1 on leaps (three stars at 80% a step or less); take 2 on 3 contrary moves |
| **imp-3-challenge: Your harmony** | A whole verse, chosen | Swing Low, Sweet Chariot's chorus (published 1872), any chord tone lit at each note; Hear it together | Lit, three stars with copied 0 and half the moves a step or less |

### Unit 4: No lights

The chords sound in every lesson here: an ear can't hear a change in a tune alone.

| Lesson | The skill | Steps | Stars from |
|---|---|---|---|
| **imp-4-hints: Lights at the changes** | The bridge from lit to unlit | Lights only where the chord changes; between, nothing; or a light that shows after you've been off for a beat | In chord, By ear said |
| **imp-4-free: Lights off** | Chord tones by ear | Why ("Lost? Hold a note until it rubs, then step"); Do: Michael, Row with no lights, its chords sounding | In chord |
| **imp-4-high: The tenor above** | A high part above the lead | Why (the tenor, a part above the lead, is part of bluegrass's high lonesome sound; light and easy, never the pressed edge); Do: Simple Gifts' verse (Joseph Brackett, 1848), lights off, a chord tone above the tune at each note | In chord, and above the tune (the chord tone above the tune's note, folded to your octave) |
| **imp-4-challenge: A trio of you** | A harmony to a tune heard once, then stacked | Red River Valley, a tune new to this course: hear it once; take 1, a harmony by ear; take 2, again ("now you know it"); take 3, a part below (or a pedal) under your take 2 (`under`); Hear it together plays the trio: you, three times. The result shows both scores ("First listen: 58%. Second: 81%") and then and now plays lesson 1 beside it | In chord on take 2, with copied 1 or fewer |

## 5. Fun

- **Hear it together** after every take: your harmony with the tune is the reward.
- **By ear** grows across the course, on every result and the course map.
- **In harmony**, the run of notes in a row on a harmony note, with a personal best (game skin).
- **The trio** at the end: three takes of you, stacked, named and shared with the Share card ("Harmony
  by ear to a tune heard once: 82% in the chord, copied 1").
- **"You just did that"**, four times: the first unlit line, the first harmony you chose yourself,
  the first fully unlit verse, and the trio.
- **Facts** (checked): a third above switches between major and minor to stay in the key; holding one
  note (a pedal) is one of the oldest harmony tricks; the tenor above the lead is part of bluegrass's
  high lonesome sound; choir singers' heart rates have been measured syncing up (Vickhoff and others,
  2013).

## 6. Safe singing

- Stop if anything tightens or hurts; don't sing when hoarse, and see someone if hoarseness lasts two
  weeks (the shared lines, build plan 3.7).
- Above a loud lead, go lighter rather than straining to match. The tune plays about 6 dB under; wired
  or one-ear headphones help you hear yourself.
- imp-4-high: light and easy, never the pressed bluegrass edge.
- Unit 4's free singing loops easily: after 15 minutes the end screen suggests a rest.

## 7. What it needs built

- From Counterpoint (built): `with`, `choices`, Hear it together, `harmony.js`, `under` (the lead's:
  an earlier step's take under this one).
- `harmony.js`: `copies`, a By ear count, leaps.
- `score: 'chordTones'`: the per-note scorer above, in `lesson-scores.js`.
- `lights`: an exercise's lights `"on"` (default), `"changes"` (only where the chord changes),
  `"late"` (a light after a beat off) or `"off"` (targets scored, never drawn). An `exercise.js`
  change, agreed with the frontend.
- The choices at each note: every chord tone in the learner's range except the tune's pitch class,
  worked out when the course file is built.

**Material**, all public domain: Michael, Row the Boat Ashore (traditional; the 1867 words, never the
1950s and 1960s added verses); Home on the Range (1870s); Shall We Gather at the River (Robert Lowry,
1864); Down to the River to Pray (traditional, first printed 1867; our own harmony); Red River Valley
(traditional, 1870s); Keep on the Sunny Side (Blenkhorn and Entwisle, 1899) or Angel Band (William
Bradbury's tune, 1860s). Not I'll Fly Away (1929) or the Carters' Can the Circle Be Unbroken (1935).
Harmony lines and exercises are ours.

## 7a. How the course file is made

`web/course/plus/improvising-harmony/course.json` is generated from the app's own song templates:
each tune's melody and chords come from `web/templates` or `server/database/templates`, the part
above is worked out note by note (the next chord tone up; a third above a passing note), and each
`choices` note lists the chord's tones in the learner's range except the tune's own. Units 3 and 4
star a chosen part with `score: "species"`: `rules: "close"` (four moves in five a step or less) or
`"contrary"` (three moves against the tune, the first partner). Shall We Gather, Down to the River to
Pray (no chords in the library), Keep on the Sunny Side and Angel Band (not in the library) gave way to
Steal Away, Swing Low and Simple Gifts, all public domain and in the library.

## 8. Later

- A **By ear** count (notes in harmony with no light showing) on every result and the course map.
- **Hint lights back** for one take, and **Tune quieter** or **an octave away**, offered after copying.
- The finale showing both takes' scores ("First listen: 58%. Second: 81%"), and **then and now**.

- Every voice's take kept for then and now across lessons, once takes are kept on the device.
- The "now be the tune too" encore in lesson 1: you sing the melody, both takes play as a duet of you.

## 9. What the reviews changed

From the **voice-teacher review**:
- "A third above" clashed on every so (ti against do): the rule is now the next chord tone up, a
  fourth over so, and a lesson (imp-1-fourth) lets you hear why.
- imp-1-switch's drill plays I, IV and V chords underneath, so "which third fits" is heard in a key.
- Lesson 1 stays lit all through, and you sing the tune first; the fading moved to imp-1-ear.
- Stars that measure the skill: imp-3-two-ways on leaps and contrary moves; the tune's own note
  never lit, so lighting can't reward copying.
- The unit 4 scorer judges per sung note with grace for passing notes and held notes, the seventh on
  V7, never counting copies; the chords sound in units 2 to 4.
- Copied counts only two or more notes in a row on the tune's pitch class, never a cadence unison.
- Ranges: every tune's do placed (section 3); Shall We Gather drops the tune an octave for the chorus.
- Claims softened: "a rub usually steps, most often down"; the tenor is part of the high lonesome
  sound.
- Skeleton lessons filled in (the hold lesson's tune and 4-beat holds; Why, Hear and Try in unit 4);
  unit 3 sings the chord before choosing; unit 4 has a bridge.
- Safety lines added; Oh! Susanna (a minstrel song) replaced by Home on the Range; Keep on the Sunny
  Side or Angel Band for the high tenor; Red River Valley called "new to this course".

From the **game-design review**:
- Copying could score near 100% in unit 4: copied notes never count, and three stars need copied 1 or
  fewer.
- Lesson 1 sings sooner (one 8-second line heard, then the long notes held), and ends selling the
  trio.
- The finale is a stack: a harmony by ear, again, then a part below under your own take: a trio of
  you, named and shared, with both scores shown.
- One measure all the way, By ear, on every result and the map; hints bridge lit to unlit.
- Help when copying or lost: Tune quieter, Tune an octave away, Sing with my guide, Hint lights back;
  the rescue move taught in unit 2.
- Variety: a listen-and-choose (Harmony or the tune?), a call and response in harmony, the pedal and
  clash lessons merged, close and against as one two-take lesson.
- Fairness: never copied or pulled on a speaker; a cadence unison never copied; "above the tune"
  defined; leaps said, never starred alone.

## Sources

The claims and facts in the course, checked 2026-10-29 (a fact-check by a music-history reviewer, and
the arranger's check of every harmony note against the templates' chords):

- Gymel, voices moving in thirds: [Britannica, gymel](https://www.britannica.com/art/gymel)
- Home on the Range (1873; Kansas's state song, 1947): [Kansapedia](https://www.kansashistory.gov/kansapedia/home-on-the-range/17165)
- Michael, Row the Boat Ashore (1863, St. Helena Island; printed 1867): [Michael, Row the Boat Ashore](https://en.wikipedia.org/wiki/Michael,_Row_the_Boat_Ashore); [Slave Songs of the United States](https://en.wikipedia.org/wiki/Slave_Songs_of_the_United_States)
- Steal Away, sung at a Choctaw Nation school in Indian Territory: [Steal Away](https://en.wikipedia.org/wiki/Steal_Away)
- Barbershop's four parts: [Barbershop Harmony Society, about our music](https://barbershop.org/music/about-our-music)
- The Sacred Harp (1844), its tune in the tenor: [Sacred Harp](https://en.wikipedia.org/wiki/Sacred_Harp); [New Georgia Encyclopedia](https://www.georgiaencyclopedia.org/articles/arts-culture/sacred-harp/); [CPDL](https://cpdl.org/wiki/index.php/The_Sacred_Harp)
- Bill Monroe and the high tenor: [Bill Monroe](https://en.wikipedia.org/wiki/Bill_Monroe); [Fred Bartenstein, bluegrass vocals](https://fredbartenstein.com/bgvocals.html)
- Chords and tunes: the app's own checked song templates (michael-row, home-on-the-range, steal-away, swing-low, simple-gifts, red-river-valley)
