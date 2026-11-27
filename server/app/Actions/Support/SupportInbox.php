<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Actions\Counts\RecordCounts;
use App\Actions\Groups\TrackRequests;
use App\Actions\Groups\TrackScores;
use App\Exceptions\Refusal;
use App\Http\Resources\Admin\AccountAdminResource;
use App\Mail\Support\SupportAnswer;
use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\SupportMessage;
use App\Models\SupportReply;
use App\Models\User;
use App\Services\Cloudflare\Turnstile;
use App\Support\Feedback\DeviceDetails;
use App\Support\Feedback\FeedbackCounts;
use App\Support\Flags;
use App\Support\Ids;
use App\Support\Trust\IpHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The support inbox (docs/operations-plan.md, "Support"): "Contact us" and "Send diagnostics" in,
 * with the app's context when the person chose to include it, and beta singers' notes; our answers out by email,
 * kept with the message. Over its target (2 working days; 1 for payments and account access) it's
 * in the digest.
 */
final class SupportInbox
{
    public function __construct(private readonly Flags $flags, private readonly Turnstile $turnstile, private readonly RecordCounts $counts) {}

    /**
     * Contact us in (docs/feedback-plan.md): only the message is needed. A guest's email is optional while feedback2 is
     * on (required while it's off, as before), an account's is its own, and "Don't reply to me" keeps none. The device
     * is kept as a kind and a browser family, never a user agent.
     *
     * @param  array{email?: string|null, reply?: bool, category?: string|null, moment?: string|null, message: string, context?: array<string, mixed>|null}  $data
     */
    public function receive(?User $user, array $data, string $ip): SupportMessage
    {
        $open = $this->flags->on('feedback2');
        $reply = ! $open || ($data['reply'] ?? true);
        $email = $reply ? ($user !== null ? $user->email : ($data['email'] ?? null)) : null;
        $email = is_string($email) && $email !== '' ? strtolower($email) : null;

        // Before feedback2, a guest's answer needs an address; an account without one still reaches us (as Send
        // diagnostics must), and staff see it's theirs and answer another way.
        if (! $open && $email === null && $user === null) {
            throw ValidationException::withMessages(['email' => __('validation.required', ['attribute' => 'email'])]);
        }

        $category = $data['category'] ?? null;
        $body = trim($data['message']);
        $message = new SupportMessage(['category' => $category ?? 'other', 'moment' => $data['moment'] ?? null, 'body' => $body, 'context' => DeviceDetails::reduce($data['context'] ?? null), 'email' => $email]);
        $message->forceFill([
            'user_id' => $user?->id,
            'status' => SupportMessage::OPEN,
            'ip_hash' => IpHash::of($ip),
            // Maybe spam: a guest's message with two or more links while Turnstile is off. Still listed.
            'maybe_spam' => $user === null && ! $this->turnstile->required() && self::links($body) >= 2,
        ])->save();

        // fb_sent:<topic>, or none when no topic was chosen: one a message, never who sent it.
        $this->counts->server(FeedbackCounts::sent($category), $user !== null, null);

        return $message;
    }

    /**
     * A beta singer's note ("Tell us"), into the inbox so it can be answered: the template, part and beat it's about,
     * and the singer, so forgetting them takes it too (ForgetSinger).
     */
    public function receiveBeta(BetaSinger $singer, string $text, ?string $templateSlug, ?string $partKey, ?float $beat, string $ip): SupportMessage
    {
        $user = $singer->user;
        $context = array_filter(['templateId' => $templateSlug, 'partKey' => $partKey, 'beat' => $beat, 'betaSinger' => $singer->sqid()], static fn (mixed $v): bool => $v !== null);
        $message = new SupportMessage(['category' => SupportMessage::BETA, 'body' => trim($text), 'context' => $context, 'email' => $user?->email !== null ? strtolower($user->email) : ($singer->email !== null ? strtolower($singer->email) : null)]);
        $message->forceFill(['user_id' => $user?->id, 'status' => SupportMessage::OPEN, 'ip_hash' => IpHash::of($ip)])->save();
        $this->counts->server(FeedbackCounts::sent(SupportMessage::BETA), true, null);

        return $message;
    }

    /** Links in a message: http(s) addresses and www. ones. */
    private static function links(string $body): int
    {
        return (int) preg_match_all('~(?:https?://|www\.)\S+~i', $body);
    }

    /**
     * Staff's answer: emailed to the person and kept; the message is answered (or closed).
     */
    public function reply(User $admin, string $sqid, string $body, bool $close): SupportMessage
    {
        return DB::transaction(function () use ($admin, $sqid, $body, $close): SupportMessage {
            $message = self::find($sqid, lock: true);
            $reply = new SupportReply(['body' => trim($body)]);
            $reply->forceFill(['support_message_id' => $message->id, 'admin_id' => $admin->id])->save();
            $message->forceFill(['status' => $close ? SupportMessage::CLOSED : SupportMessage::ANSWERED, 'answered_at' => now(), 'closed_at' => $close ? now() : null])->save();

            if ($message->email !== null) {
                Mail::to($message->email)->locale($message->user?->preferredLocale() ?? 'en')->queue((new SupportAnswer($reply, $message->user?->name))->afterCommit());
            }

            return $message;
        });
    }

    public function setStatus(string $sqid, string $status, ?User $admin = null, ?string $ip = null): SupportMessage
    {
        $message = self::find($sqid);
        $before = $message->status;
        $message->forceFill(['status' => $status, 'closed_at' => $status === SupportMessage::CLOSED ? now() : null])->save();
        AuditLog::record('support.status', $message, $admin, before: ['status' => $before], after: ['status' => $status], ip: $ip);

        return $message;
    }

    /**
     * Open messages past their target: 1 working day for payments and account access, else 2.
     */
    public static function overdue(): int
    {
        return SupportMessage::query()->where('status', SupportMessage::OPEN)
            ->where(fn ($query) => $query->where(fn ($q) => $q->whereIn('category', ['payments', 'account'])->where('created_at', '<', now()->subWeekdays(1)))
                ->orWhere('created_at', '<', now()->subWeekdays(2)))
            ->count();
    }

    public static function find(string $sqid, bool $lock = false): SupportMessage
    {
        return SupportMessage::query()->with(['user', 'replies.admin'])->whereKey(Ids::decode($sqid) ?? 0)->when($lock, fn ($query) => $query->lockForUpdate())->first() ?? throw new Refusal(404, 'not_found');
    }

    /**
     * A tracks request's own fields, as groups.tracks.create kept them, with where it's got to, the score's files
     * (with 10-minute download links in full) and the rights record Give this song takes (TrackRequests).
     *
     * @return array<string, mixed>
     */
    private static function tracks(SupportMessage $message, bool $full): array
    {
        $tracks = (array) ($message->context['tracks'] ?? []);
        $text = static fn (string $key): ?string => is_string($tracks[$key] ?? null) ? $tracks[$key] : null;

        return [
            'groupId' => $text('groupId'), 'group' => $text('group'), 'piece' => $text('piece') ?? '', 'composer' => $text('composer'), 'voicing' => $text('voicing'),
            'rights' => $text('rights'), 'rightsConfirmedAt' => $text('rightsConfirmedAt'), 'rightsStatement' => $text('rightsStatement'),
            'status' => TrackRequests::status($message),
            'scores' => TrackScores::forMessage($message, links: $full),
            'song' => TrackRequests::song($message),
            'deliveredAt' => $text('deliveredAt'),
            'give' => TrackRequests::give($message),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function view(SupportMessage $message, bool $full = false): array
    {
        return [
            'id' => $message->sqid(),
            'category' => $message->category,
            'status' => $message->status,
            // The message in full says their plan too, so a payment question is answered with it.
            'from' => ['email' => $message->email, 'account' => $message->user !== null ? ['id' => $message->user->sqid(), 'name' => $message->user->name, ...($full ? ['plan' => AccountAdminResource::plan($message->user)] : [])] : null],
            'preview' => mb_strimwidth($message->body, 0, 140, '...'),
            'createdAt' => $message->created_at?->utc()->toIso8601ZuluString(),
            'answeredAt' => $message->answered_at?->utc()->toIso8601ZuluString(),
            // Where it came from, the Maybe spam mark, and No reply address (docs/feedback-plan.md).
            'moment' => $message->moment,
            'maybeSpam' => $message->maybe_spam,
            'canReply' => $message->email !== null,
            // A director's learning tracks request: the group, the piece and their word on its rights, so Admin can
            // give the song straight from the row (admin.people.songs.create to from.account).
            ...($message->category === SupportMessage::TRACKS ? ['tracks' => self::tracks($message, $full)] : []),
            ...($full ? [
                'body' => $message->body,
                'context' => $message->context,
                'replies' => $message->replies->map(fn (SupportReply $r): array => ['body' => $r->body, 'by' => $r->admin !== null ? ['id' => $r->admin->sqid(), 'name' => $r->admin->name] : null, 'sentAt' => $r->created_at?->utc()->toIso8601ZuluString()])->values()->all(),
                'savedReplies' => array_map(static fn (string $key): array => ['key' => $key, 'title' => (string) __('support.saved.'.$key.'.title'), 'body' => (string) __('support.saved.'.$key.'.body')], ['silent_mic', 'out_of_time', 'iphone', 'refunds', 'refunds_store', 'cancelling', 'lost_account', 'tracks_score']),
            ] : []),
        ];
    }
}
