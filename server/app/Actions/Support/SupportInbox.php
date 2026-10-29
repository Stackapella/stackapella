<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Exceptions\Refusal;
use App\Http\Resources\Admin\AccountAdminResource;
use App\Mail\Support\SupportAnswer;
use App\Models\AuditLog;
use App\Models\SupportMessage;
use App\Models\SupportReply;
use App\Models\User;
use App\Support\Ids;
use App\Support\Trust\IpHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The support inbox (docs/operations-plan.md, "Support"): "Contact us" and "Send diagnostics" in,
 * with the app's context when the person ticked to include it; Hillel's answers out by email,
 * kept with the message. Over its target (2 working days; 1 for payments and account access) it's
 * in the digest.
 */
final class SupportInbox
{
    /**
     * @param  array{email?: string|null, category: string, message: string, context?: array<string, mixed>|null}  $data
     */
    public function receive(?User $user, array $data, string $ip): SupportMessage
    {
        $email = $user->email ?? ($data['email'] ?? null);

        // Signed out, the answer needs an address; an account without one still reaches Hillel (as
        // Send diagnostics must), who sees it's theirs and answers another way.
        if (($email === null || $email === '') && $user === null) {
            throw ValidationException::withMessages(['email' => __('validation.required', ['attribute' => 'email'])]);
        }

        $message = new SupportMessage(['category' => $data['category'], 'body' => trim($data['message']), 'context' => $data['context'] ?? null, 'email' => $email !== null && $email !== '' ? strtolower($email) : null]);
        $message->forceFill(['user_id' => $user?->id, 'status' => SupportMessage::OPEN, 'ip_hash' => IpHash::of($ip)])->save();

        return $message;
    }

    /**
     * Hillel's answer: emailed to the person and kept; the message is answered (or closed).
     */
    public function reply(User $admin, string $sqid, string $body, bool $close): SupportMessage
    {
        return DB::transaction(function () use ($admin, $sqid, $body, $close): SupportMessage {
            $message = self::find($sqid, lock: true);
            $reply = new SupportReply(['body' => trim($body)]);
            $reply->forceFill(['support_message_id' => $message->id, 'admin_id' => $admin->id])->save();
            $message->forceFill(['status' => $close ? SupportMessage::CLOSED : SupportMessage::ANSWERED, 'answered_at' => now(), 'closed_at' => $close ? now() : null])->save();

            if ($message->email !== null) {
                Mail::to($message->email)->queue((new SupportAnswer($reply, $message->user?->name))->afterCommit());
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
            ...($full ? [
                'body' => $message->body,
                'context' => $message->context,
                'replies' => $message->replies->map(fn (SupportReply $r): array => ['body' => $r->body, 'by' => $r->admin !== null ? ['id' => $r->admin->sqid(), 'name' => $r->admin->name] : null, 'sentAt' => $r->created_at?->utc()->toIso8601ZuluString()])->values()->all(),
                'savedReplies' => array_map(static fn (string $key): array => ['key' => $key, 'title' => (string) __('support.saved.'.$key.'.title'), 'body' => (string) __('support.saved.'.$key.'.body')], ['silent_mic', 'out_of_time', 'iphone', 'refunds', 'refunds_store', 'cancelling', 'lost_account']),
            ] : []),
        ];
    }
}
