<?php

declare(strict_types=1);

namespace App\Actions\Mudeo;

use App\Exceptions\Refusal;
use App\Jobs\SendMudeoBatch;
use App\Mail\Mudeo\MudeoInvite;
use App\Models\AuditLog;
use App\Models\EarlyAccessLink;
use App\Models\EmailSuppression;
use App\Models\MudeoBatch;
use App\Models\MudeoContact;
use App\Models\Setting;
use App\Models\User;
use App\Support\Accounts\EarlyAccess;
use App\Support\Http\Hosts;
use App\Support\Ids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use SplFileObject;
use Throwable;

/**
 * The list for Hillel's one email to mudeo's users (docs/mudeo-transition-plan.md): imported from
 * mudeo's CSV with the plan's cleaning, and sent through the app 50 at a time, one email each, from
 * stackapella.com (config mail.mudeo) (or, as a fallback, handed out to paste and marked sent by hand). A daily
 * cap holds (200 to start); bounces and unsubscribes are taken off and suppressed for good. Every
 * change is in the audit log, and the list goes 90 days after its last batch (the opt-outs stay).
 */
final class MudeoList
{
    public const BATCH = 50;

    public const DAILY_CAP = 200;

    public const KEEP_DAYS = 90;

    /** Stop for the day above this share of a batch bouncing (the screen says so). */
    public const BOUNCE_WARN = 0.05;

    /** The admin's daily cap, when changed from DAILY_CAP. */
    public const CAP = 'mudeo.daily_cap';

    /** How a pruned contact's hashed address starts. */
    public const HASHED = 'hmac:';

    /** Relay addresses that reach a person. */
    private const KEPT_RELAYS = ['privaterelay.appleid.com'];

    /** Obviously not a person: test and placeholder domains. */
    private const TEST_DOMAINS = ['example.com', 'example.org', 'example.net', 'test.com', 'test', 'localhost', 'invalid', 'mailinator.com', 'yopmail.com'];

    /**
     * @return array{imported: int, skipped: array{invalid: int, duplicate: int, test: int, suppressed: int, hasAccount: int, alreadyListed: int}}
     */
    public function import(string $path, User $admin, ?string $ip): array
    {
        $skipped = ['invalid' => 0, 'duplicate' => 0, 'test' => 0, 'suppressed' => 0, 'hasAccount' => 0, 'alreadyListed' => 0];
        $rows = [];
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $header = null;

        foreach ($file as $line) {
            /** @var list<string|null>|false $line */
            if (! is_array($line) || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(static fn (?string $name): string => strtolower(trim((string) $name)), $line);

                if (! in_array('email', $header, true)) {
                    throw ValidationException::withMessages(['file' => __('validation.mudeo_csv')]);
                }

                continue;
            }

            $row = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), null));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $domain = substr((string) strrchr($email, '@'), 1);

            $reason = match (true) {
                filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255 => 'invalid',
                isset($rows[$email]) => 'duplicate',
                ! in_array($domain, self::KEPT_RELAYS, true) && (in_array($domain, self::TEST_DOMAINS, true) || str_ends_with($domain, '.test') || str_ends_with($domain, '.invalid') || str_ends_with($domain, '.example')) => 'test',
                default => null,
            };

            if ($reason !== null) {
                $skipped[$reason]++;

                continue;
            }

            $rows[$email] = [
                'email' => $email,
                'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 255) ?: null,
                'mudeo_id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : null,
                'mudeo_created_at' => self::date($row['created_at'] ?? null),
                'mudeo_updated_at' => self::date($row['updated_at'] ?? null),
            ];
        }

        $imported = 0;

        foreach (array_chunk($rows, 500, true) as $chunk) {
            $emails = array_keys($chunk);
            $suppressed = array_flip(EmailSuppression::query()->whereIn('email', $emails)->pluck('email')->all());
            $accounts = array_flip(User::query()->whereIn('email', $emails)->pluck('email')->all());
            $listed = array_flip(MudeoContact::query()->whereIn('email', [...$emails, ...array_map(self::hashed(...), $emails)])->pluck('email')->all());
            $new = [];

            foreach ($chunk as $email => $row) {
                $reason = match (true) {
                    isset($listed[$email]), isset($listed[self::hashed($email)]) => 'alreadyListed',
                    isset($suppressed[$email]) => 'suppressed',
                    // Hillel's open question 3: those already on Stackapella are left out for now.
                    isset($accounts[$email]) => 'hasAccount',
                    default => null,
                };

                if ($reason !== null) {
                    $skipped[$reason]++;

                    continue;
                }

                $new[] = [...$row, 'created_at' => now(), 'updated_at' => now()];
            }

            $imported += count($new);
            MudeoContact::query()->insertOrIgnore($new);
        }

        AuditLog::record('mudeo.imported', null, $admin, after: ['imported' => $imported, 'skipped' => $skipped], ip: $ip);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * The next batch: the most recently active first, nobody sent, opted out or bounced.
     *
     * @return array{ids: list<string>, emails: list<string>, line: string, fits: bool, remaining: int}
     */
    public function next(): array
    {
        $contacts = $this->waiting()->orderByDesc('mudeo_updated_at')->orderBy('id')->limit(self::BATCH)->get(['id', 'email']);
        $emails = array_values(array_map(static fn (MudeoContact $c): string => $c->email, $contacts->all()));

        return [
            'ids' => array_values(array_map(static fn (MudeoContact $c): string => $c->sqid(), $contacts->all())),
            'emails' => $emails,
            'line' => implode(', ', $emails),
            'fits' => $this->sentToday() + count($emails) <= $this->limit(),
            'remaining' => $this->waiting()->count(),
        ];
    }

    /**
     * These were sent in one Bcc: marked, as a batch, under today's limit.
     *
     * @param  list<string>  $sqids
     */
    public function send(array $sqids, User $admin, ?string $ip): MudeoBatch
    {
        return DB::transaction(function () use ($sqids, $admin, $ip): MudeoBatch {
            // One at a time, so two batches at once can't pass the limit together.
            Setting::query()->where('key', self::CAP)->lockForUpdate()->first();
            MudeoBatch::query()->lockForUpdate()->latest('id')->first();
            $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Ids::decode($sqid), $sqids)));
            $contacts = MudeoContact::query()->whereKey($ids)->lockForUpdate()->get();

            if ($ids === [] || $contacts->count() !== count(array_unique($ids)) || $contacts->contains(fn (MudeoContact $c): bool => $c->status() !== 'to_send')) {
                throw ValidationException::withMessages(['ids' => __('validation.mudeo_ids')]);
            }

            $today = $this->sentToday();
            $limit = $this->limit();

            if ($today + $contacts->count() > $limit) {
                throw new Refusal(409, 'daily_limit', ['sentToday' => $today, 'limit' => $limit]);
            }

            $batch = new MudeoBatch;
            $batch->forceFill(['count' => $contacts->count(), 'mode' => MudeoBatch::MANUAL, 'status' => MudeoBatch::SENT, 'delivered' => $contacts->count(), 'sent_at' => now(), 'created_by' => $admin->id])->save();
            MudeoContact::query()->whereKey($contacts->modelKeys())->update(['sent_at' => now(), 'mudeo_batch_id' => $batch->id, 'updated_at' => now()]);
            AuditLog::record('mudeo.batch_sent', $batch, $admin, after: ['count' => $batch->count], ip: $ip);

            return $batch;
        });
    }

    /**
     * The last batch didn't go: its addresses are waiting again.
     */
    public function undo(User $admin, ?string $ip): void
    {
        DB::transaction(function () use ($admin, $ip): void {
            $batch = MudeoBatch::query()->whereNull('undone_at')->lockForUpdate()->latest('id')->first() ?? throw new Refusal(404, 'not_found');

            // Those emails really went.
            if ($batch->mode === MudeoBatch::APP) {
                throw new Refusal(409, 'sent_by_app');
            }
            MudeoContact::query()->where('mudeo_batch_id', $batch->id)->update(['sent_at' => null, 'mudeo_batch_id' => null, 'updated_at' => now()]);
            $batch->forceFill(['undone_at' => now()])->save();
            AuditLog::record('mudeo.batch_undone', $batch, $admin, before: ['count' => $batch->count], ip: $ip);
        });
    }

    /**
     * Opted out ("no more") or bounced: off the list, and never mailed by the app either.
     *
     * @param  list<string>  $emails
     * @param  'opted_out'|'bounced'  $as
     * @return array{marked: int, notFound: list<string>}
     */
    public function mark(array $emails, string $as, User $admin, ?string $ip): array
    {
        $emails = array_values(array_unique(array_filter(array_map(static fn (string $e): string => strtolower(trim($e)), $emails))));
        $found = MudeoContact::query()->whereIn('email', $emails)->pluck('email')->all();

        DB::transaction(function () use ($found, $as, $admin, $ip): void {
            MudeoContact::query()->whereIn('email', $found)->update([$as.'_at' => now(), 'updated_at' => now()]);

            foreach ($found as $email) {
                EmailSuppression::record($email, $as === 'opted_out' ? 'mudeo_opt_out' : 'mudeo_bounce');
            }

            AuditLog::record('mudeo.marked', null, $admin, after: ['as' => $as, 'count' => count($found)], ip: $ip);
        });

        return ['marked' => count($found), 'notFound' => array_values(array_diff($emails, $found))];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $last = MudeoBatch::query()->whereNull('undone_at')->latest('id')->first();
        $link = EarlyAccessLink::query()->where('label', EarlyAccessLink::MUDEO)->get(['id']);
        $limit = $this->limit();

        return [
            'total' => MudeoContact::query()->count(),
            'sent' => MudeoContact::query()->whereNotNull('sent_at')->count(),
            'toSend' => $this->waiting()->count(),
            'optedOut' => MudeoContact::query()->whereNotNull('opted_out_at')->count(),
            'bounced' => MudeoContact::query()->whereNotNull('bounced_at')->count(),
            'sentToday' => $this->sentToday(),
            'warnAt' => (int) floor($limit * 0.8),
            'limit' => $limit,
            'dailyCap' => $limit,
            'newsReady' => self::newsReady(),
            'postalAddressSet' => self::postalAddressSet(),
            'lastBatch' => $last !== null ? [
                'id' => $last->sqid(),
                'count' => $last->count,
                'sentAt' => $last->sent_at->utc()->toIso8601ZuluString(),
                'mode' => $last->mode,
                'status' => $last->status,
                'delivered' => $last->delivered,
                'bounced' => $last->bounced,
                'failed' => $last->failed,
                'bounceRate' => $last->bounceRate(),
                'error' => $last->error,
            ] : null,
            'importedAt' => MudeoContact::query()->max('created_at') !== null ? Carbon::parse((string) MudeoContact::query()->max('created_at'))->utc()->toIso8601ZuluString() : null,
            'joined' => User::query()->whereIn('early_access_link_id', $link->modelKeys())->count(),
            'days' => $this->days($link->modelKeys()),
        ];
    }

    /**
     * The email's results by day (Hillel, 2026-10-29: "Anonymous totals"), newest first, over the last
     * 60 days: what was sent and bounced, the link opened, the accounts made through it, and the
     * people from it who started singing. Daily numbers only, never per person.
     *
     * @param  array<int, int|string>  $links
     * @return list<array{date: string, sent: int, bounced: int, visits: int, joined: int, used: int}>
     */
    private function days(array $links): array
    {
        $since = now()->subDays(59)->startOfDay();
        $sent = $bounced = $visits = $used = $joined = [];

        foreach (MudeoBatch::query()->where('sent_at', '>=', $since)->whereNull('undone_at')->get(['sent_at', 'delivered', 'bounced', 'failed']) as $batch) {
            $day = $batch->sent_at->toDateString();
            $sent[$day] = ($sent[$day] ?? 0) + $batch->delivered + $batch->bounced + $batch->failed;
            $bounced[$day] = ($bounced[$day] ?? 0) + $batch->bounced;
        }

        foreach (DB::table('usage_counts')->where('day', '>=', $since->toDateString())->whereIn('kind', ['mudeo_visit', 'mudeo_used'])->selectRaw('day, kind, sum(count) as n')->groupBy('day', 'kind')->get() as $row) {
            if ($row->kind === 'mudeo_visit') {
                $visits[(string) $row->day] = (int) $row->n;
            } else {
                $used[(string) $row->day] = (int) $row->n;
            }
        }

        foreach (User::query()->whereIn('early_access_link_id', $links)->where('created_at', '>=', $since)->selectRaw('date(created_at) as day, count(*) as n')->groupBy('day')->get() as $row) {
            $joined[(string) $row->getAttribute('day')] = (int) $row->getAttribute('n');
        }

        $dates = array_unique(array_map('strval', [...array_keys($sent), ...array_keys($visits), ...array_keys($used), ...array_keys($joined)]));
        rsort($dates);

        return array_map(static fn (string $day): array => [
            'date' => $day,
            'sent' => $sent[$day] ?? 0,
            'bounced' => $bounced[$day] ?? 0,
            'visits' => $visits[$day] ?? 0,
            'joined' => $joined[$day] ?? 0,
            'used' => $used[$day] ?? 0,
        ], $dates);
    }

    public function setCap(int $cap, User $admin, ?string $ip): void
    {
        $before = $this->limit();
        $setting = Setting::query()->firstOrNew(['key' => self::CAP]);
        $setting->forceFill(['value' => $cap, 'updated_by' => $admin->id])->save();
        AuditLog::record('mudeo.daily_cap', null, $admin, before: ['dailyCap' => $before], after: ['dailyCap' => $cap], ip: $ip);
    }

    /**
     * Send the next batch through the app: up to 50 reserved for it now (so two can't take the
     * same people), under today's cap, then sent one by one by SendMudeoBatch.
     */
    public function start(User $admin, ?string $ip): MudeoBatch
    {
        self::ensureReady();

        $batch = DB::transaction(function () use ($admin, $ip): MudeoBatch {
            Setting::query()->where('key', self::CAP)->lockForUpdate()->first();

            if (MudeoBatch::query()->where('status', MudeoBatch::SENDING)->lockForUpdate()->exists()) {
                throw new Refusal(409, 'batch_sending');
            }

            $ids = $this->waiting()->orderByDesc('mudeo_updated_at')->orderBy('id')->limit(self::BATCH)->lockForUpdate()->pluck('id')->all();

            if ($ids === []) {
                throw new Refusal(409, 'nothing_to_send');
            }

            $today = $this->sentToday();
            $limit = $this->limit();

            if ($today + count($ids) > $limit) {
                throw new Refusal(409, 'daily_limit', ['sentToday' => $today, 'limit' => $limit]);
            }

            $batch = new MudeoBatch;
            $batch->forceFill(['count' => count($ids), 'mode' => MudeoBatch::APP, 'status' => MudeoBatch::SENDING, 'sent_at' => now(), 'created_by' => $admin->id])->save();
            MudeoContact::query()->whereKey($ids)->update(['mudeo_batch_id' => $batch->id, 'updated_at' => now()]);
            AuditLog::record('mudeo.batch_started', $batch, $admin, after: ['count' => $batch->count], ip: $ip);

            return $batch;
        });

        SendMudeoBatch::dispatch($batch->id);

        return $batch;
    }

    /**
     * SendMudeoBatch's work: one email to each of the batch's people not yet sent, each recorded
     * the moment it goes, so a retry after the worker dies never mails anyone twice. A bounce
     * Cloudflare reports takes the address off the list; one that couldn't go, or wasn't reached
     * before the time ran out, waits for a later batch. An address suppressed before its turn is
     * taken off without counting as this batch's bounce.
     */
    public function deliver(MudeoBatch $batch, int $seconds = 240): void
    {
        $link = EarlyAccess::url(EarlyAccess::current());
        $until = microtime(true) + $seconds;
        $error = null;
        $contacts = MudeoContact::query()->where('mudeo_batch_id', $batch->id)->whereNull('sent_at')->whereNull('bounced_at')->whereNull('opted_out_at')->orderBy('id')->get();

        foreach ($contacts as $contact) {
            if (microtime(true) > $until) {
                break;
            }

            $before = EmailSuppression::query()->where('email', $contact->email)->value('reason');

            if (is_string($before)) {
                $contact->forceFill([str_contains($before, 'bounce') ? 'bounced_at' : 'opted_out_at' => now()])->save();

                continue;
            }

            try {
                Mail::to($contact->email)->send(new MudeoInvite(self::firstName($contact->name), $link, self::unsubscribeUrl($contact)));
            } catch (Throwable $e) {
                report($e);
                $error = mb_substr($e->getMessage(), 0, 500);
                $contact->forceFill(['mudeo_batch_id' => null])->save();
                $batch->increment('failed');

                continue;
            }

            // Cloudflare's answer records a permanent bounce as a suppression at once.
            $bounced = EmailSuppression::query()->where('email', $contact->email)->exists();
            $contact->forceFill([$bounced ? 'bounced_at' : 'sent_at' => now()])->save();
            $batch->increment($bounced ? 'bounced' : 'delivered');
        }

        // Anyone not reached in time waits for a later batch.
        MudeoContact::query()->where('mudeo_batch_id', $batch->id)->whereNull('sent_at')->whereNull('bounced_at')->whereNull('opted_out_at')->update(['mudeo_batch_id' => null, 'updated_at' => now()]);

        $batch->refresh();
        $batch->forceFill(['status' => $batch->delivered === 0 && $batch->failed > 0 ? MudeoBatch::FAILED : MudeoBatch::SENT, 'error' => $error])->save();
        AuditLog::record('mudeo.batch_sent', $batch, null, after: ['delivered' => $batch->delivered, 'bounced' => $batch->bounced, 'failed' => $batch->failed]);
    }

    /**
     * Every try failed: what didn't go waits for a later batch, and the batch says why.
     */
    public function abandon(MudeoBatch $batch, ?string $why): void
    {
        MudeoContact::query()->where('mudeo_batch_id', $batch->id)->whereNull('sent_at')->update(['mudeo_batch_id' => null, 'updated_at' => now()]);
        $batch->forceFill(['status' => MudeoBatch::FAILED, 'error' => $why !== null ? mb_substr($why, 0, 500) : null])->save();
    }

    /**
     * Send a test to me: the email as a person gets it, to the admin's own address (its
     * unsubscribe link says it's a test).
     */
    public function test(User $admin, ?string $ip): string
    {
        self::ensureReady();

        $address = (string) $admin->email;
        Mail::to($address)->send(new MudeoInvite(self::firstName($admin->name), EarlyAccess::url(EarlyAccess::current()), Hosts::public(URL::signedRoute('mudeo.unsubscribe', ['contact' => 'test'], absolute: false))));
        AuditLog::record('mudeo.test_sent', null, $admin, ip: $ip);

        return $address;
    }

    /**
     * Unsubscribed in one click: off the list, and never mailed by the app again.
     */
    public function unsubscribe(MudeoContact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $contact->forceFill(['opted_out_at' => $contact->opted_out_at ?? now()])->save();

            // A pruned contact has no address left to suppress, and its hash keeps it off any re-import.
            if (! $contact->isForgotten()) {
                EmailSuppression::record($contact->email, 'mudeo_opt_out');
            }
            AuditLog::record('mudeo.unsubscribed', null, null, after: ['contact' => $contact->id]);
        });
    }

    public static function unsubscribeUrl(MudeoContact $contact): string
    {
        return Hosts::public(URL::signedRoute('mudeo.unsubscribe', ['contact' => $contact->sqid()], absolute: false));
    }

    public static function newsReady(): bool
    {
        return config('mail.mudeo.ready') === true && filter_var((string) config('mail.mudeo.address'), FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function postalAddressSet(): bool
    {
        return trim((string) config('mail.mudeo.postal_address')) !== '';
    }

    private static function ensureReady(): void
    {
        if (! self::newsReady()) {
            throw new Refusal(409, 'news_not_set_up');
        }

        if (! self::postalAddressSet()) {
            throw new Refusal(409, 'postal_address_missing');
        }
    }

    private static function firstName(?string $name): ?string
    {
        $first = trim((string) strtok(trim((string) $name), " \t"));

        return $first !== '' && ! str_contains($first, '@') ? mb_convert_case($first, MB_CASE_TITLE) : null;
    }

    /**
     * @return list<array{id: string, email: string, name: string|null, status: string, sentAt: string|null}>
     */
    public function search(string $q): array
    {
        $q = strtolower(trim($q));

        $found = MudeoContact::query()->where('email', 'like', '%'.addcslashes($q, '%_\\').'%')->where('email', 'not like', self::HASHED.'%')->orderBy('email')->limit(20)->get()->all();

        return array_values(array_map(static fn (MudeoContact $c): array => ['id' => $c->sqid(), 'email' => $c->email, 'name' => $c->name, 'status' => $c->status(), 'sentAt' => $c->sent_at?->utc()->toIso8601ZuluString()], $found));
    }

    /**
     * The nightly clean-up: 90 days after the last batch went, the list goes, but for its
     * opt-outs (kept as the reason their address is suppressed). Those it mailed keep only a keyed
     * hash of the address in its place, so a re-import never mails them again and their
     * unsubscribe link still finds them.
     */
    public function prune(): int
    {
        $last = MudeoBatch::query()->whereNull('undone_at')->max('sent_at');

        if ($last === null || Carbon::parse((string) $last)->gt(now()->subDays(self::KEEP_DAYS))) {
            return 0;
        }

        $forgotten = 0;
        $mailed = MudeoContact::query()->whereNull('opted_out_at')->whereNotNull('sent_at')->where('email', 'not like', self::HASHED.'%');

        foreach ($mailed->lazyById() as $contact) {
            $contact->forceFill(['email' => self::hashed($contact->email)])->save();
            $forgotten++;
        }

        $forgotten += MudeoContact::query()->whereNull('opted_out_at')->whereNull('sent_at')->delete();
        MudeoContact::query()->update(['name' => null, 'mudeo_id' => null, 'mudeo_created_at' => null, 'mudeo_updated_at' => null]);

        return $forgotten;
    }

    /**
     * What a pruned contact keeps of their address: a hash keyed by the app's key, never the
     * address itself.
     */
    public static function hashed(string $email): string
    {
        return self::HASHED.hash_hmac('sha256', $email, (string) config('app.key'));
    }

    public function sentToday(): int
    {
        return (int) MudeoBatch::query()->whereNull('undone_at')->where('sent_at', '>=', now()->startOfDay())->sum('count');
    }

    public function limit(): int
    {
        $cap = Setting::query()->where('key', self::CAP)->value('value');

        return is_numeric($cap) ? (int) $cap : self::DAILY_CAP;
    }

    /**
     * @return Builder<MudeoContact>
     */
    private function waiting(): Builder
    {
        return MudeoContact::query()->whereNull('sent_at')->whereNull('mudeo_batch_id')->whereNull('opted_out_at')->whereNull('bounced_at');
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '' || strtotime($value) === false) {
            return null;
        }

        return Carbon::parse($value)->utc();
    }
}
