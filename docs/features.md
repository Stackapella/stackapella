# What each plan gets

**Status:** written for launch readiness (L, 2026-10-27), by the backend agent. This is the single
list of what Free and Plus get ([pricing-plan.md](pricing-plan.md), "Keeping claims honest"), as
Event Schedule's `docs/FEATURES.md` is. The pricing page, the plan gates and the in-app labels say
what this says. **The claims test** (`server/tests/Feature/Ops/FeaturesClaimsTest.php`) fails the
build when a number below differs from the code, or a Plus-only row isn't refused to a free account
where it says.

## The story

- **Free: learn to sing harmony.** The whole beginner course, four songs (plus a song of the month),
  recording, Make it sound good, share links and videos.
- **Plus: the whole library, and everything you make.** Every song, with new ones each month; the
  full course and unlimited workouts; your history; unlimited projects and more storage; MP3 and
  stems.

## The numbers

Each row's value is checked against the config it names.

| Claim | Free | Plus | Config |
|---|---|---|---|
| Saved projects you own (course work never counts) | 3 | Unlimited | `usage.plans.*.projects` |
| Storage for recordings (the bin never counts) | 1 GB | 50 GB | `usage.plans.*.storage_bytes` |
| Friends singing on one of your projects | 2 | 20 | `usage.plans.*.singers` |
| Private or unlisted templates | 1 | Unlimited | `usage.plans.*.templates` |

| Claim | Value | Config |
|---|---|---|
| Plus, monthly, in US dollars | $4.99 | `billing.amounts.usd.month` |
| Plus, yearly, in US dollars | $39.99 | `billing.amounts.usd.year` |
| Plus, monthly, in euros (tax included) | €4.99 | `billing.amounts.eur.month` |
| Plus, yearly, in euros (tax included) | €39.99 | `billing.amounts.eur.year` |
| Plus, monthly, in pounds (tax included) | £4.49 | `billing.amounts.gbp.month` |
| Plus, yearly, in pounds (tax included) | £34.99 | `billing.amounts.gbp.year` |
| The free trial, once per account and once per card | 7 days | `billing.trial_days` |
| A Plus song stays usable offline after the last check | 14 days | `billing.offline_days` |
| Stackapella's commission on coaching | 15% | `coaching.commission_percent` |
| Singers in a choir group | 60 | `usage.group_members` |
| Groups one director can run | 5 | `usage.groups_per_director` |
| The longest take | 600 seconds | `usage.take_max_seconds` |
| A project invite link works for | 14 days | `usage.invite_days` |

**The free songs** (`templates.free`): Amazing Grace (`amazing-grace`), Are You Sleeping
(`are-you-sleeping`), Michael Row the Boat Ashore (`michael-row`), Happy Birthday
(`happy-birthday`, added with the launch library). Every other official song is Plus, except the song of the month, which is
free to everyone for its calendar month (and projects started from it stay usable afterwards).
Every song can be previewed by anyone.

## Free and Plus, side by side

| | Free | Plus |
|---|---|---|
| **Songs and learning** | | |
| Official songs | The four above, and the song of the month | All of them, with new ones each month |
| Preview any song (hear it, see its parts) | Yes | Yes |
| The course | Units 1 to 7 | All units and paths |
| Today's workout | One a day | Unlimited, with the full exercise library |
| The report on your latest take | Yes | Yes, with history and trends |
| **Making and keeping** | | |
| Saved projects, storage, friends on a project | As above | As above |
| Export the mix as WAV, with Make it sound good | Yes | Yes |
| Export the mix as MP3 | No | Yes |
| Export each part on its own (stems) | Only by "Download and delete" | Yes |
| **Sharing** | | |
| Share links and share videos | Yes | Yes |
| **Templates** | | |
| Create and publish a public template | Yes | Yes |
| Private or unlisted templates | As above | Unlimited |
| Your own recorded guides | No | Yes |
| Choir groups: share templates and assign parts (18 and over) | No | Yes |
| **Coaching** | | |
| Buy take reviews, lessons and packages | Yes, at the coach's price | Yes, at the coach's price |
| Send to my teacher (18 and over) | No | Yes |
| **Trial** | | 7 days |

Coaches get Plus free while they're active, and Stackapella keeps the commission above.

## Where Plus is enforced

A free account is refused each of these with `403 plus_required` (the app shows the upgrade
moment). The claims test checks each method refuses it.

| Plus only | Refused in |
|---|---|
| A Plus song's template (not the song of the month) | `App\Http\Controllers\Api\TemplatesController::show` |
| Your score history (`scores.list`) | `App\Http\Controllers\Api\Billing\ScoresController::index` |
| Starting a choir group | `App\Actions\Groups\ManageGroups::create` |
| Your own recorded guides | `App\Actions\Templates\TemplateRecordings::start` |
| Send to my teacher | `App\Actions\Coaching\Conversations::inviteTeacher` |

**In the app only** (the server never sees them): MP3 and stems export, the workout count, the
course units past 7. The frontend's tests check those gates.

---

[Back: Pricing](pricing-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs)
