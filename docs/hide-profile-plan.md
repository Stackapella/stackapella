# Hide profile

**Status:** backend built (2026-10-29), by the backend agent from Hillel's request via the
coordinator. The frontend builds the setting; the landing agent's public pages need no change (they
read the same helpers).

Hillel: "add a 'Hide profile' option in the settings so you can easily keep songs without your info
being attached to a public profile, ie in the future gallery and in the app. We wouldn't show names,
we'd show anonymous".

## The setting

- `users.hide_profile` (boolean, default false; in the export as `hideProfile`). `account.show` and `account.update` gain
  `hideProfile`. It never blocks sharing, recording, posting or joining anything.
- It overrides `publicProfile` (the existing opt-in profile page, N1): while hidden there is no
  profile page (`profiles.show` 404), no follow button, and no profile link anywhere. `publicProfile`
  keeps its stored value, so turning Hide profile off brings the page back as it was.

## One rule, one place

Everything shown to other people goes through one helper, `GalleryViews::person()` (and its
`name()`), with `Gallery::hidden()` as the test: a hidden account is `{ name: "Anonymous", handle:
null, avatarUrl: null }`. A query that loads people with a column list selects `hide_profile`. "Anonymous" is a new word (lang `sharing.anonymous`),
translated like the rest.

The sweep moves every direct `->name` shown to others onto it:

| Where | Today | Hidden |
|---|---|---|
| Gallery: harmonies, comments, open parts, the parts' singers | `GalleryViews::person` | Anonymous |
| Shared song pages, link previews, cards, video credits (`HarmonyFacts::singers`) | own name filter | Anonymous, no avatar |
| Part leaderboards and challenges (`Boards`) | `person()['name']` | Anonymous |
| Leagues (`Leagues` overrides the handle) | name and handle | Anonymous, no handle |
| Bells to others (likes, follows, board_beaten) | `person()` | Anonymous |
| The library's creator, a remix's parent | `person()` | Anonymous |
| Featured social posts (`SocialPoster`) | the owner's name | "A Stackapella singer", no network handle |
| Ideas and Sing together credits (`TellWriters`, `SingTogetherPagesController`) | `person()` | Anonymous |
| Virtual choir credits (`Choirs`) | per-voice credit, already has Anonymous | Anonymous |
| A remix's "persons" template credit (`PersonsTemplate`) | the name | Anonymous |
| Guide voices and course clips (beta singers) | `BetaSinger::credit()` | see below |

## Where the name stays (people you chose)

- **Shared songs and choir groups** (`CollaborationController`, `GroupView`, consent requests): the
  people in them were invited by you or invited you, and need to know who sang what. They still see
  your name. (The coordinator's call; Hillel can overrule.)
- **Coaching** with your own coach or student: a paid one-to-one relationship; both see the name.
  A coach who hides their profile is left out of the coach directory and search (their page 404s).
- **Admins** always see the real person (`admin.person.json`): moderation and support.

## The beta singers' credit

A beta singer chooses how a guide voice is credited (`credit_mode`: their name, or anonymous). That
choice is made for that credit alone and agreed in the beta terms, so it stands: a hidden account
credited by name keeps the name on its guide voices and course clips, but never a profile link
(`creditHandle` null). One who wants no name there chooses the anonymous credit, as today.

## Contract

- `account.show` / `account.update`: `hideProfile` (boolean).
- `person.json`'s description: the name may be "Anonymous", with `handle` and `avatarUrl` null.
  No shape changes elsewhere: values only.
- `profiles.show`: 404 for a hidden account, as for any non-public one.

## Open, for Hillel (built as below; say if not)

1. The gallery today lists only harmonies whose owner has a public profile. Hillel's words suggest
   a hidden person can still put songs in the gallery, shown as Anonymous. Built that way: a hidden
   adult (who otherwise could) may list a harmony, without the public profile. Comments by them
   show as Anonymous too.
2. Friends in shared songs and choir groups still see the name (above).
3. A hidden beta singer credited by name keeps the name on guides (above).
