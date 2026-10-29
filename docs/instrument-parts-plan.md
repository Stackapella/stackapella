# Instrument parts (a contract proposal)

**Status (2026-10-27): agreed with the frontend agent; server side built 2026-10-27** (the minimum app version waits for the frontend's first build with free keys). Hillel
approved the feature ([decisions.md](decisions.md), "voices first, instruments welcome"). Once the
frontend agrees, the changes below go into `contract/` with `CONTRACT_OK=1` and a line in
`contract/log.md`, and the server side is built behind them. The creator's screens and the pitch
feedback per part are the frontend's.

## What changes

1. **Free part keys** in a template, for people's own templates.
2. **Each part says what it is:** `kind: "voice" | "instrument"`.
3. **A free part name** (already a required field; its length is now capped).
4. **Up to 6 parts** in people's own templates; official templates stay SATB.
5. **A project's track says what it is too** (`kind` on a part), so credits say "played by" for an
   instrument.
6. **The wording:** "Sung by" or "Played by" on share pages, the share card, emails and posts.

Nothing changes for an existing template, project or client that never uses an instrument part.

## `template.json`

The part (`$defs.part`):

| Field | Today | Proposed |
|---|---|---|
| `key` | `enum: soprano, alto, tenor, bass` | `pattern: ^[a-z][a-z0-9-]{0,39}$` (stable, unique in the template, never shown) |
| `kind` | (none) | optional, `enum: voice, instrument`; **absent means `voice`** |
| `name` | required string | required string, 1 to 40 characters, no links (the text rules) |
| `range` | MIDI low and high | unchanged; for an instrument, the notes it plays |
| `melody` | true on one part | unchanged; an instrument may carry the melody |

The rules the schema can't say (`TemplateRules`, and its twin `validateTemplate()` in
`web/js/templates.js`):
- **people's own templates:** 1 to 6 parts;
- **official templates** (`SyncOfficialTemplates`): every key is one of soprano, alto, tenor and
  bass, and every part is a voice (`kind` absent or `voice`). A file that breaks this fails the
  sync, as a schema problem does today;
- `voicings` keep naming parts by key, from 1 to the number of parts.

The app names a part from its key when the key is one of the four (in the person's language, as
`templatePartName()` does now) and from `name` otherwise. A free key is never shown.

## `part.json` (a project's track)

| Field | Proposed |
|---|---|
| `kind` | `enum: voice, instrument`, default `voice`. In `parts.save` it's optional (absent keeps what's stored, or `voice` for a new part); in every answer it's always there |

A part made from a template part takes that part's `kind` (the app sends it); the person can change
it in the studio (a project's own parts aren't tied to the template's).

**Server:** a `kind` column on `parts` (`voice` by default, so every existing part stays a voice).

## Where `kind` reaches credits

- **A shared harmony** (`harmonies.create`) keeps each track's `kind` in its snapshot of the
  tracks (beside each track's name and colour), so a later change in the project doesn't rewrite a
  published page. Its credits stay the people on it (the owner and the singers), not one person per
  track, so the credit line says what the tracks are, not who played which.
- **Ideas, open parts, choirs and the beta:** ideas and open parts take the part's `kind` from the
  template; the virtual choir and the beta are official songs, so always voices.
- **`guide.recording.json`**, the template's voices: unchanged. The part's `kind` is in the
  template beside it.

## The wording

| Where | Today | With instruments |
|---|---|---|
| Share page and card (`sharing.sung_by`) | "Sung by Ana and Bo" | every track a voice: "Sung by Ana and Bo" (as now); some instruments: "Sung and played by Ana and Bo"; every track an instrument: "Played by Ana and Bo" |
| Share description | ":names sang :song in harmony" | ":names sang and played :song", or ":names played :song" |
| Posts (`social.featured`) | ":title, sung by :by" | the same three forms |
| Idea emails (`ideas.mail`) | ":name sang the :part of your tune" | ":name played the :part of your tune" for an instrument part |

The words go in `sharing.php`,
`social.php` and `ideas.php`, with the app's own strings the frontend's.

## Compatibility

- **Old data:** every stored template and part has no `kind`, so it reads as a voice; every
  stored key is one of the four. Nothing is migrated except the new column's default.
- **Old clients reading new templates:** the web already names an unknown key from `name`. But an
  old build's `validateTemplate()` refuses a key outside the four, so a template with an
  instrument part would fail to open there. **The fix:** when this ships, the minimum app version
  (`CheckAppVersion`, `426 app_outdated`) moves to the first build that knows free keys, so an old
  install is told to update rather than shown an error. The native apps do the same from their
  first release.
- **Old clients writing:** a `parts.save` without `kind` keeps the stored one, so an old client
  never turns an instrument back into a voice.
- **Column sizes:** every stored part key is already up to 40 characters (the beta's 20 hold only
  official keys).

## Not in this change

- Pitch feedback per part (off by default for an instrument, with "Show my pitch"): the frontend's.
- Instrument sounds for the practice synth: the real-instrument samples
  ([decisions.md](decisions.md)), hosted on the media CDN (a separate proposal).

## Questions for the frontend

1. Is `kind` optional in `template.json` (absent is a voice) fine, or should every new template
   write it?
2. "Sung by", "Sung and played by" and "Played by", from the tracks' kinds: the same forms in the
   app? (One person per track would need each track's recorder in the share; not proposed.)
3. The minimum app version: which build will be the first with free keys?

---

[Back: Decisions](decisions.md) · [Docs guide](../README.md#a-guide-to-the-docs)
