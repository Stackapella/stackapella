# Clips to record: real singers for the launch courses

Status: 2026-10-29 (courses-styles, for the coordinator). Every clip slot in
`course/plus/clips.json` (26), grouped into four sessions of about 20 minutes, most useful first.
Nothing is recorded yet. Before Hillel can send the first batch, the admin needs a way to ask for a
clip (see [What Hillel does](#what-hillel-does-to-send-the-first-batch)).

Back: [Plus courses](../plus-courses-plan.md) · [Docs guide](../../README.md#a-guide-to-the-docs)

## How a clip works

A course names a clip by its slot id (`"clip": "pop.breathy"`) on a listening round or a
self-check's model. Until a singer's take is accepted, the step plays its fallback: the guide voice
playing the same notes (`guide`), the step's words alone (`description`), or, for `skip`, the round
is left out. Accepting a take publishes it to its slot, and every learner hears it on their next
visit; no course change is needed. Each slot's `sing` line is the singer's brief, shown on their My
parts screen with the clip's length and where to pitch it from their comfortable note.

Two rules for every session:

- **One singer records a whole session, in one sitting, on one mic.** Most rounds compare two or
  three clips (straight against vibrato, a clean flip against a slide); they only teach if the one
  difference is the one we ask for.
- **Every slot is "any voice".** The briefs pitch from the singer's own comfortable note, so a
  lower or higher voice both work. Two voices for one slot can come later.

## The sessions, by impact

### Session 1: Pop's tone colours and its styled line (a pop or country singer)

The biggest gap: the guide's one "oo" voice can't make a tone colour at all, so Three colours today
describes breathy, bright and twang in words only (the lead's launch call). Real takes turn it into
three models to copy.

| Slot | Lesson | Sing | Length |
|---|---|---|---|
| `pop.breathy` | Pop and country 3.1, Three colours | Down in the Valley's first line, breathy and close: a little air, quiet, never a whisper | 10 s |
| `pop.bright` | Pop and country 3.1, Three colours | The same line, clear and forward at a medium volume, not belted | 10 s |
| `pop.twang` | Pop and country 3.1, Three colours | The same line with a little twang: narrow and bright, never pressed or nasal | 10 s |
| `pop.styled` | Pop and country 2.3, Which move? | The same line, a scoop up into "val-" from about a third below and a fall-off on "low" | 10 s |

Four takes of one ten-second line: about 15 minutes with retakes, so a singer with time left can
go on to session 2.

### Session 2: Breath onsets and ends (any singer)

Breath into note's listening step has six rounds, but today it plays only the two about sliding
into a note. The four that teach breathy, hard and balanced starts (`skip`) stay hidden until these
three clips exist, because the guide can't make a puff of air or a glottal click.

| Slot | Lesson | Sing | Length |
|---|---|---|---|
| `breath.onset-balanced` | Breath 3.1, Breath into note | "Ah" on the comfortable note, the breath turning straight into tone: no click, no puff | 2 s |
| `breath.onset-breathy` | Breath 3.1, Breath into note | "Ah" with an audible puff of air just before the tone | 2 s |
| `breath.onset-hard` | Breath 3.1, Breath into note | "Ah" starting with a light click in the throat (a glottal start), never pressed | 2 s |
| `breath.end-steady` | Breath 2.3, End on purpose | A held "oo" for about 6 seconds, stopped on purpose while still steady | 6 s |
| `breath.end-ranout` | Breath 2.3, End on purpose | The same, the last second sagging and wobbling as if out of air: imitated gently, never strained | 6 s |

The guide plays the two ends today, but a sagging synth note doesn't sound like a singer running
out; a real one does. About 12 minutes.

### Session 3: Yodeling's flips (a yodeler, or a singer who flips cleanly on purpose)

The guide voice has one register, so its "flip" is only a jump in pitch: Flip or slide? and Clean
or smeared? ask learners to hear a register break the guide can't make. Real flips are what the
course is about.

| Slot | Lesson | Sing | Length |
|---|---|---|---|
| `yodel.hook-clean` | Yodeling 1.3, Flip or slide? | "Yo-hee-o": low, high, low (a sixth up and back), flipping cleanly each way, where the singer's voice flips | 3 s |
| `yodel.hook-slid` | Yodeling 1.3, Flip or slide? | The same "yo-hee-o", sliding between the notes instead of flipping | 3 s |
| `yodel.roll-clean` | Yodeling 4.3, Clean or smeared? | Low, high, low, high, low, quickly, every flip clean | 3 s |
| `yodel.roll-smeared` | Yodeling 4.3, Clean or smeared? | The same, the flips smeared into slides | 3 s |

Short clips, but each pair needs the one difference only, so allow retakes: about 12 minutes.

### Session 4: Vibrato (a singer with an easy, natural vibrato)

The guide's vibrato is a decent model already (rate, width and a little unevenness), so these come
after the three gaps above. They make Hear the difference, Echo the guide and Straight, then bloom
sound like a person.

| Slot | Lesson | Sing | Length |
|---|---|---|---|
| `vibrato.straight` | Vibrato 1.4, Hear the difference | One held "ah" on the comfortable note with no wave at all | 3 s |
| `vibrato.natural` | Vibrato 1.4 and 2.3, Echo the guide | The same with the singer's own easy vibrato | 3 s |
| `vibrato.wobble` | Vibrato 1.4 | A slow, wide wobble, about three waves a second | 3 s |
| `vibrato.straight-high` | Vibrato 1.4 | As straight, a fourth higher | 3 s |
| `vibrato.natural-high` | Vibrato 1.4 | As natural, a fourth higher | 3 s |
| `vibrato.bloom` | Vibrato 1.4 and 4.1, Straight, then bloom | Straight for about a second and a half, then the vibrato let in | 5 s |
| `vibrato.wide` | Vibrato 2.3, Echo the guide | Easy, with a clearly wider vibrato than the singer's natural one | 3 s |
| `vibrato.steered` | Vibrato 2.3, Echo the guide | Deliberate, even slurs between two close notes, about three a second | 3 s |

Eight short holds: about 18 minutes.

### Not yet: slots no lesson plays

`pop.plain`, `pop.slide-cry`, `yodel.ho`, `yodel.hoo` and `yodel.down` are defined but no step
names them yet. Don't send them until a lesson uses one (its `used` list in clips.json says so); a
course agent adds a slot to a lesson and this page together.

## What Hillel does to send the first batch

The server side is ready: the deploy's `app:templates:sync` loads every slot into the catalogue,
`admin.beta.assignments.create` takes a `clipId`, the singer's app shows a clip first on My parts
with its brief, and accepting the take publishes it to its slot. The admin screens aren't ready
yet (the frontend's, sent to the coordinator):

1. **Singers, Add part** asks only for a song and a part. It needs a "Course clip" choice listing
   the open slots (`admin.beta.clips.list`, unused today) and sending `clipId` with the minutes. A
   way to add a whole session at once would match this page.
2. **A singer's parts list** shows a song title and part name; a clip has neither, so its row would
   be blank. It needs the slot and its brief, and Copy message needs a clip's wording.
3. **Review** reads `item.template.title` and its version for every take; a clip take has no
   template (`kind: "clip"`, its `clip` brief instead), so the queue would fail to draw as soon as
   one arrives. It needs the brief in place of the song, and no song filter or version chip.

Once those land, for each session:

1. Pick a beta singer who fits the session (Singers screen), or invite one.
2. Add the session's clips to them, 20 minutes in all, and send them the copied message with their
   link.
3. When their takes arrive, listen in Review against the brief. Accept to publish (the lesson plays
   it on the next visit), or ask for a retake with a kind note. A pair that differs in more than the
   one asked-for way (louder, another vowel) is a retake.
4. Check the lesson once: the round plays the singer instead of the guide.
