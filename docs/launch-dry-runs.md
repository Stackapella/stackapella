# Launch dry runs on the beta

**Status:** written 2026-10-29 by the backend agent (C in the coordinator's queue). The code side was
run locally and passed; the runs on the beta need Hillel, because they use the beta's console and
its real services. Each step says where, the exact command, what you should see, and what to do if
you don't. Tick the launch checklist's row when a step passes.

## What ran locally, 2026-10-29

| Run | Result |
|---|---|
| `php artisan app:doctor` | No failure in the code. The two FAILs were this machine's (no queue worker or scheduler running); the WARNs are the keys the beta and launch set (Stripe live, X and Bluesky, VAPID, Sentry, the bounce Worker) |
| `php tool/abuse-test.php http://127.0.0.1:8000` | Every attack stopped where abuse-test.md says: sign-ups at the 6th, resets at the 4th, sign-in guesses at the 11th, made-up app tokens at the 31st, resends never refused |
| Backup and restore drill | Passed on the dev server the day before (the doctor's "Restore drill: last one passed") |

## 1. The readiness checks (H1)

**Where:** App Platform, the `web` component's Console.

```
php artisan app:doctor
```

**You should see:** no FAIL. Each WARN is a key that's still to come; read its Fix column.

| You see | Do |
|---|---|
| FAIL Queue: the oldest job has waited | The `scheduler` component isn't draining it: check it's running and its logs |
| FAIL Scheduler: never run | The `scheduler` component (`schedule:work`) isn't running |
| FAIL Database: migrations not up to date | The pre-deploy `migrate` job failed: read its log, then deploy again |
| FAIL anything else | Its Fix column says what; send it to the coordinator if it's not clear |

## 2. A backup and the restore drill (H2)

**Where:** the same console.

```
php artisan app:backup:run
php artisan app:backup:drill
```

**You should see:** the backup's size and where it went, then "Restored ... into a scratch database,
checked every table" with its rows and keys. The doctor then shows both as passed.

| You see | Do |
|---|---|
| The drill can't create a database | The managed MySQL user can't: create one database with "drill" in its name in DigitalOcean (Databases, Users & Databases), set `RESTORE_DRILL_DATABASE` to it, and run the drill again |
| `mysqldump` not found | The buildpack's `Aptfile` includes `mysql-client`; redeploy, or set `MYSQL_CLIENT_PATH` |
| The backup can't write | Check `BACKUP_SPACES_KEY` and `_SECRET` are the backup bucket's own key |

## 3. The abuse test (H15)

**Where:** your Mac, in `~/Code/stackapella/server` (it attacks from your network, so the limits
see one network, as an attacker's would).

```
php tool/abuse-test.php https://beta.stackapella.com --email-domain=<a domain whose mail you receive>
```

Use a domain with a catch-all in Cloudflare Email Routing, so the made-up sign-ups' mail lands
somewhere and nothing bounces. It refuses stackapella.com itself.

**You should see:** "Every attack stopped where the plan says." Afterwards your network is limited on
the beta for up to an hour: that's the test working. Then the by-hand checks in
abuse-test.md, "By hand, signed in on the beta domain".

| You see | Do |
|---|---|
| An attack stopped later than it should | Send the line to the coordinator: a limiter isn't applied on the beta (or Cloudflare is hiding the real IP: check `BEHIND_CLOUDFLARE`) |
| Every answer is 403 from Cloudflare | Cloudflare's own bot rules stopped it first. That's fine for safety, but the test didn't reach us: pause Bot Fight Mode for the beta while it runs |

## 4. Bounce readiness (H9)

**Where:** Cloudflare, then App Platform.

1. Deploy the bounce Worker (the runbook's "Bounces" section), with `MAIL_WEBHOOK_SECRET` set as
   its secret.
2. Set the same `MAIL_WEBHOOK_SECRET` in App Platform (encrypted), and deploy.
3. Run `php artisan app:doctor` again: "Bounce webhook" passes.
4. Send one email to an address that can't exist at a domain you own (for example a password reset
   for it); the Worker's log in Cloudflare (Workers, its Logs) shows the bounce delivered to
   `/webhooks/mail` with a 2xx answer.

## What needs Hillel

All four steps above: they run on the beta's console, on Cloudflare, or from your own network. The
code needs nothing more for them. If any step doesn't show what it should, send the line to the
coordinator and the backend agent fixes it.

---

[Back: The launch checklist](launch-checklist.md)
