# The beta: a guide for Hillel

How to run the singer beta from the admin (`/admin`): inviting singers, reviewing their takes,
publishing real voices, and handling a removal request. The plan behind it is
[beta-singers-plan.md](beta-singers-plan.md). The app sends singers nothing: every link and every
status message goes from you.

## Before the first invite

- **A song opens for recording once it's reviewed.** Singers see "This song opens for recording
  once our musician has checked it" until the template version is marked `reviewed`
  (templates-review.md). You can invite and assign parts before that.
- **Sign in** at `/admin` with Google, then the second factor. Changes that can't be undone (paid,
  forget, removing a guide) ask for the second factor again if it was more than a short while ago.

## Inviting a singer

1. **Singers** > **Invite a singer**: their name, an email for your records (the app never writes
   to it), a note only you see, the hourly rate and currency, an optional expiry, and the parts
   with the minutes you pay for each.
2. **The link shows once.** Copy it, or on a phone use **Share** (Messages, WhatsApp). If it's lost
   or forwarded, **New link** replaces it: the old one stops at once.
3. The singer opens it, reads the short agreement, says they're 18 or over, and chooses how
   they're credited (a name, or anonymous). Their card then says **Agreed**.
4. On an iPhone, the installed app doesn't share Safari's sign-in. My parts has **Use another
   device**, which shows a 6-character code to type into the app's **Beta singer? Enter your
   code** link.

**More parts later:** **Add a part** on their card. A part can be removed until they've sent a
take for it.

## What singers do

My parts lists their parts. **Learn and record** opens the song in the studio's beta mode: the key
is the song's own, the first take needs a measured timing check and a 10-second sound check (two
quiet seconds, then singing), and **Check and send** shows what went well, then anything to fix
(level, clipping, noise, other parts heard in the mic, pitch and entries), with the phrases worth
a loop. Sending is always allowed. The take waits on their device until it has reached you, and is
sent again automatically if the connection drops.

**Tell Hillel** (in My parts and the studio) sends you a note with the song, part and place.

## Reviewing a take

**Review** lists the takes waiting, newest first; filter by status, song and part. Opening one
marks it **In review** for the singer.

- **Listen:** **Play** plays it with the other parts: real voices where there are some, the synth,
  or on its own. **Their take / Synth guide** switches this part between the take and the synth
  (A/B). **Real voices where there are some** is the whole song in real voices, to judge the blend.
- **The chart** shows the part's notes with their singing over them. Click a phrase to play from
  it, and to pin a retake note to it. The checks and their own note are listed beside it.
- **Fix lightly** instead of asking for a retake: **Nudge** (later is positive), **Trim the
  start** (song time), **Trim the end**, and **Level** (on top of the levelling every guide gets,
  to about -20 dBFS). You hear each change as you make it; **Save fixes** keeps them, and they're
  saved for you if you move on.
- **Decide:**
  - **Accept**: it's published in a moment, levelled and faded, and becomes the voice learners
    hear, unless you untick "Make it the voice learners hear" (then it's an alternate).
  - **Ask for another take**: a note they see in My parts, pinned to a phrase they can loop. Say
    what went well too.
  - **Not this one**: with an optional kind note.
- **Keys** (they can be turned off on the queue page): J and K next and previous, Space play, B
  switch between the take and the synth, A accept.
- **Editors** can listen and ask for retakes; only you fix, accept and reject.

A take recorded for an older version of a song's notes says so. Its voice only plays with the notes
it was sung to, so ask for a new take if the part changed.

## Telling a singer

The app sends nothing. On a singer's card, **Copy a status message** next to a part copies a short,
friendly message for its status (ready to record, arrived, another take asked, now the guide, kept
as an alternate, not used) to paste into your own message.

## Real voices

**Real voices** shows each song's parts: "3 of 4 parts are real voices", what's still needed, the
**Default** voice per part and its **Alternates**. **Make default** switches it for learners at
once. Learners hear a real voice in the song's own key at full speed; in another key or speed they
hear the synth, with a note saying why.

## Paying singers

Each card shows what's **Owed** (accepted parts not yet paid) and what's been paid. Pay them as
you usually do, outside the app, then **Mark as paid** (it asks for the second factor).

## A removal request

- **One voice:** **Real voices** > **Remove** on that guide. The part falls back to an alternate,
  or the synth.
- **Everything of theirs:** **Forget this singer** on their card (type their name to confirm).
  Their voices come off the app, their takes and notes are deleted, and their name, email and
  credit are cleared. Payment records stay. It can't be undone, and it can't reach copies people
  exported (the agreement says so).

## Feedback

**Feedback** is the Tell Hillel inbox, new notes first. **See the takes for this part** opens the
queue filtered to that song and part. **Mark as read** when you've dealt with it.

---

[Back: Beta singers](beta-singers-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)
