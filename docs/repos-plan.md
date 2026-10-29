# Three repos

**Status:** approved by Hillel 2026-10-29 ("Keep the history"); hillelcoren/stackapella-courses exists. Switched: the content lives in the courses repo, pinned by `.do/content.lock`. Hillel's call: three repos,
the main one (backend, web app, site), the Flutter apps under PolyForm Strict, and a public
courses repo, open to pull requests, with every course and the library songs.

## 1. What goes where

| Repo | What's in it | Licence | Who can see it |
|---|---|---|---|
| `stackapella` (today's) | `server/`, `web/` (the app and the site), `contract/`, `docs/`, the tools | AAL (today's `LICENSE`) | Public when the open-source plan says so |
| `stackapella-mobile` (exists) | The Flutter apps | PolyForm Strict 1.0.0 (source-available, no use without a licence) | Private for now; public is Hillel's call |
| `stackapella-courses` (new) | Every course and the library's songs, with their format and its checks | `CONTENT-LICENSE`, CC BY-SA 4.0, contributions inbound = outbound | Public, open to issues and pull requests |

What moves into the courses repo, from today's main repo:

| Today | In the courses repo |
|---|---|
| `web/course/course.json` (the free course) | `course/course.json` |
| `web/course/plus/` (every Plus course, `courses.json`, `clips.json`) | `course/plus/` |
| `web/course/format/` (`steps.json`, `course.schema.json`, the README) | `course/format/` |
| `web/templates/*.json` and `index.json` (the songs the app bundles) | `templates/free/` |
| `server/database/templates/*.json` (the Plus library songs) | `templates/library/` |
| `contract/schemas/template.json` (a copy, kept in step) | `templates/format/template.json` |
| `web/tool/course_check.py` | `tools/course_check.py` |
| A new `tools/template_check.py` (the template rules the schema can't say, from `TemplateRules`) | `tools/template_check.py` |

What stays in main: the guides (`server/resources/guides`), What's new, the pictures, and every test
that runs the app's own code against the content (`web/tests/course.test.js`,
`plus-courses.test.js` and the per-course ones, the server's sync and arrangement tests). Those run
in main against the pinned content (section 3), so a content change that breaks the app is caught
before it reaches anyone.

## 2. How the server and the web app read it

**The paths don't change.** At build, the content is put back where the code already looks for it:
`course/` at `web/course/`, `templates/free/` at `web/templates/`, `templates/library/` at
`server/database/templates/`. So `config/templates.php`, `SitePages`, `CourseClips`, the web app's
`/course/...` and `/templates/...` URLs, nginx and the service worker all work as today, with no code
change.

**Pinned, never "latest".** Main holds `.do/content.lock`: the courses repo's URL and one commit. Every
build fetches exactly that commit, so a deploy is reproducible and content changes reach production
only through a reviewed bump in main.

- **Production (App Platform):** the Dockerfile fetches the pinned commit's tarball from GitHub
  (`https://codeload.github.com/<owner>/stackapella-courses/tar.gz/<commit>`), checks its sha256
  (also in `.do/content.lock`), and unpacks it into those three paths. The repo is public, so this needs
  no deploy key or token. If the fetch or the checksum fails, the build fails: never a deploy
  without its songs.
- **CI (GitHub Actions):** the same fetch, the same checksum, before the server and web jobs.
- **Local (the agents and Hillel):** `server/tool/content.sh` links the three paths to a checkout
  of the courses repo. It uses `CONTENT_DIR` when set (an agent's own working copy), else a sibling
  `~/Code/stackapella-courses-content` at the pinned commit, which it clones or updates. The three
  paths are in main's `.gitignore`, so nothing from the content repo is committed to main by
  mistake.
- **Fallback:** if the paths are already there (today's tree, or a checkout before the move), the
  script leaves them alone. So the move can be done in steps without breaking anyone.

**Why not a git submodule:** App Platform's builds and our worktrees handle submodules badly (each
agent worktree needs its own init, and a detached submodule is easy to commit wrongly), and a
submodule doesn't give us the checksum. A pinned tarball does both with one file.

## 3. What runs at deploy, and when content changes

The deploy's run command stays `php artisan migrate --force && php artisan app:templates:sync`. The
sync reads the same paths, so it is unchanged:

- **Templates:** new songs and new versions load; `CarryVoicesOver` carries real voices to a new
  version and alerts on any it can't.
- **Clips:** `CourseClips::sync()` loads `course/plus/clips.json`, retiring slots that left it.
- **Plus courses' catalogue:** `SitePages` and the app read `course/plus/courses.json` as they do now.

**A content change, start to finish:**

1. A course agent, the arranger or an outside contributor opens a pull request on the courses repo.
2. The courses repo's CI runs `course_check.py` (the format) and `template_check.py` (the schema
   and the rules) on it. Outside contributions are reviewed by Hillel or the courses lead.
3. Merged. Then a **bump** in main: `.do/content.lock` set to the new commit (with its checksum).
   `server/tool/content-bump.sh <commit>` writes both and links the new content locally.
4. Main's CI and the landing gate run everything against the new content: the server suite (the
   sync, the arrangement checks, the clips), the web suite (every course test), and the contract
   check. Green, it lands and deploys like any other change.

Who bumps: the coordinator, or the agent whose content it is, through the normal landing.

## 4. How the agents work

- **Course agents and the arranger** work in the courses repo, in their own worktrees, committing
  to its `main` as they commit to ours today (small commits, "Area: outcome", no trailers). They
  test against the app by pointing `CONTENT_DIR` at their worktree, running main's web suite and
  the server's sync locally. When their work is in, they bump main (or ask the coordinator to).
- **The backend and frontend** never edit content in main any more. A code change that needs new
  content (a new step kind, say) lands its format change in the courses repo first (`steps.json`,
  the validator), then the code and the bump in main.
- **The mobile apps** bundle `templates/free` and the free course from the same pinned commit: the
  mobile repo gets its own `.do/content.lock` and a script that copies them into `assets/`, replacing
  today's hand copies.

## 5. The order of the move

1. **Hillel:** create `stackapella-courses` on GitHub (public, under the same owner), with
   `CONTENT-LICENSE`, `CONTRIBUTING.md` (the courses section of today's) and branch protection on
   `main` (pull requests for outside contributors, CI required). No deploy key is needed, as it's
   public.
2. **Backend:** add `.do/content.lock`, `content.sh`, `content-bump.sh`, the Dockerfile fetch with the
   checksum, and the CI fetch, all inert while the paths are still in the tree (the fallback). Land.
3. **Backend:** push the content to the new repo. With `git filter-repo` keeping its history (the
   three paths only), or as one first commit pointing to main's history: Hillel's choice, history
   recommended. Add its CI (`course_check.py`, `template_check.py`, the template schema).
4. **Backend:** set `.do/content.lock` to that first commit. Check, on the beta, that a build from the
   fetched content is byte-identical to today's tree (a `diff -r` of the three paths in the image).
5. **Coordinator:** a short freeze on content edits in main (tell the course agents and the
   arranger).
6. **Backend:** remove the three paths from main, add them to `.gitignore`, update the README's setup
   (`sh server/tool/content.sh` after cloning), `docs/architecture.md`, `open-source-plan.md` (the
   courses repo and the apps under PolyForm Strict) and the agents' briefs. Land and deploy. The
   freeze ends.
7. **Mobile agent:** its `.do/content.lock` and copy script.

Undo, at any step before 6: delete `.do/content.lock`; the tree still has everything. After 6: put the
paths back from the last commit before the removal.

## 6. What Hillel must do

- Create the `stackapella-courses` repo (public), and say whether to keep the content's history.
- Decide when the Flutter repo's licence changes to PolyForm Strict (a `LICENSE` swap in the mobile
  repo; the lawyer should see it with the other licences, H14).
- Nothing in App Platform: no new settings or keys, as the build fetches a public tarball.

## 7. Open

- Outside pull requests on songs: the arranger's review rules (ranges, voicings, the arrangement
  check) apply; who merges them is Hillel's or the courses lead's call.
- The content repo's issues may get feature requests for the app; a template for "this belongs in
  the app" pointing to main.
