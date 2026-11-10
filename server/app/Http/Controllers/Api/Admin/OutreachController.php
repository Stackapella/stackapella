<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Outreach\ManageTargets;
use App\Actions\Outreach\OutreachReplies;
use App\Actions\Outreach\OutreachResults;
use App\Actions\Outreach\SendOutreach;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Outreach\DraftOutreachRequest;
use App\Http\Requests\Admin\Outreach\EditOutreachDraftRequest;
use App\Http\Requests\Admin\Outreach\FileOutreachReplyRequest;
use App\Http\Requests\Admin\Outreach\ImportOutreachRequest;
use App\Http\Requests\Admin\Outreach\ListOutreachRequest;
use App\Http\Requests\Admin\Outreach\LogOutreachReplyRequest;
use App\Http\Requests\Admin\Outreach\StoreOutreachTargetRequest;
use App\Http\Requests\Admin\Outreach\SuppressOutreachRequest;
use App\Http\Requests\Admin\Outreach\UpdateOutreachTargetRequest;
use App\Http\Resources\Admin\OutreachResource;
use App\Models\AuditLog;
use App\Models\EmailSuppression;
use App\Models\OutreachMessage;
use App\Models\OutreachReply;
use App\Models\OutreachSuppression;
use App\Models\OutreachTarget;
use App\Models\User;
use App\Support\Ids;
use App\Support\Outreach\Outreach;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * admin.outreach.* (docs/outreach-plan.md): the organizations, their drafts and sends, replies,
 * results and the do-not-contact list. Admin only; every change audited.
 */
final class OutreachController extends Controller
{
    public function index(ListOutreachRequest $request): JsonResponse
    {
        $status = $request->validated('status');
        $q = $request->validated('q');
        $targets = OutreachTarget::query()->with(['messages', 'replies', 'partnerCodes'])
            ->when(is_string($status) && $status !== 'follow_up_due', fn ($query) => $query->where('status', $status))
            ->when(is_string($request->validated('group')), fn ($query) => $query->where('group', strtolower((string) $request->validated('group'))))
            ->when(is_string($request->validated('angle')), fn ($query) => $query->where('angle', $request->validated('angle')))
            ->when(is_string($q) && $q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes((string) $q, '%_\\').'%')->orWhere('contact_email', 'like', '%'.addcslashes((string) $q, '%_\\').'%')))
            ->orderBy('name')->get();

        // Both do-not-contact lists read once for every listed address, not once a row.
        $emails = $targets->pluck('contact_email')->filter()->map(static fn (mixed $e): string => strtolower((string) $e))->values()->all();
        $suppressed = $emails === [] ? [] : array_values(array_unique([
            ...OutreachSuppression::query()->whereIn('email', $emails)->pluck('email')->all(),
            ...EmailSuppression::query()->whereIn('email', $emails)->pluck('email')->all(),
        ]));
        $rows = $targets->map(static fn (OutreachTarget $t): array => OutreachResource::target($t, array_map('strval', $suppressed)));

        if ($status === 'follow_up_due') {
            $rows = $rows->where('followUpDue', true);
        }

        return response()->json([
            'data' => $rows->values()->all(),
            'summary' => [
                'configured' => Outreach::configured(),
                'cap' => Outreach::cap(),
                // First emails only, the cap's count: follow-ups never use it up.
                'sentToday' => Outreach::firstsSentToday(),
                'followUpsDue' => Outreach::followUpsDue(),
                'unreadReplies' => Outreach::unreadReplies(),
                'unfiledReplies' => OutreachReply::query()->whereNull('outreach_target_id')->count(),
                'sendBySoon' => Outreach::sendBySoonCount(),
            ],
            'unfiled' => OutreachReply::query()->whereNull('outreach_target_id')->latest('received_at')->limit(50)->get()->map(static fn (OutreachReply $r): array => OutreachResource::reply($r))->values()->all(),
        ]);
    }

    public function store(StoreOutreachTargetRequest $request, ManageTargets $targets, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var array{name: string, group: string, angle: string} $fields */
        $fields = $request->validated();

        return response()->json(['data' => OutreachResource::detail($targets->create($fields, $admin, $request->ip()))], 201);
    }

    public function import(ImportOutreachRequest $request, ManageTargets $targets, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values((array) $request->validated('rows'));

        return response()->json(['data' => $targets->import($rows, $admin, $request->ip())]);
    }

    public function results(OutreachResults $results): JsonResponse
    {
        return response()->json(['data' => $results->handle()]);
    }

    public function show(OutreachTarget $target): JsonResponse
    {
        return response()->json(['data' => OutreachResource::detail($target)]);
    }

    public function update(UpdateOutreachTargetRequest $request, OutreachTarget $target, ManageTargets $targets, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => OutreachResource::detail($targets->update($target, $request->validated(), $admin, $request->ip()))]);
    }

    public function draft(DraftOutreachRequest $request, OutreachTarget $target, SendOutreach $send, #[CurrentUser] User $admin): JsonResponse
    {
        $send->draft($target, (string) ($request->validated('kind') ?? OutreachMessage::FIRST), $admin, $request->ip());

        return response()->json(['data' => OutreachResource::detail($target->refresh())], 201);
    }

    public function editDraft(EditOutreachDraftRequest $request, OutreachMessage $message, SendOutreach $send, #[CurrentUser] User $admin): JsonResponse
    {
        $subject = $request->validated('subject');
        $body = $request->validated('body');

        return response()->json(['data' => OutreachResource::message($send->edit($message, is_string($subject) ? $subject : null, is_string($body) ? $body : null, $admin, $request->ip()))]);
    }

    public function discardDraft(Request $request, OutreachMessage $message, SendOutreach $send, #[CurrentUser] User $admin): Response
    {
        $send->discard($message, $admin, $request->ip());

        return response()->noContent();
    }

    public function send(Request $request, OutreachMessage $message, SendOutreach $send, #[CurrentUser] User $admin): JsonResponse
    {
        $send->send($message, $admin, $request->ip(), $request->boolean('staleOk'), $request->boolean('earlyOk'));

        return response()->json(['data' => OutreachResource::detail($message->target->refresh())]);
    }

    public function markSent(Request $request, OutreachMessage $message, SendOutreach $send, #[CurrentUser] User $admin): JsonResponse
    {
        $send->markSent($message, $admin, $request->ip(), $request->boolean('staleOk'), $request->boolean('earlyOk'));

        return response()->json(['data' => OutreachResource::detail($message->target->refresh())]);
    }

    public function test(Request $request, OutreachMessage $message, SendOutreach $send, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => $send->test($message, $admin, $request->ip())]);
    }

    public function logReply(LogOutreachReplyRequest $request, OutreachTarget $target, OutreachReplies $replies, #[CurrentUser] User $admin): JsonResponse
    {
        $at = $request->validated('receivedAt');
        $from = $request->validated('from');
        $subject = $request->validated('subject');
        $replies->receive(is_string($from) ? $from : (string) $target->contact_email, is_string($subject) ? $subject : null, (string) $request->validated('body'), is_string($at) ? Carbon::parse($at)->utc() : now(), $target, $admin, $request->ip());

        return response()->json(['data' => OutreachResource::detail($target->refresh())], 201);
    }

    public function readReply(FileOutreachReplyRequest $request, OutreachReply $reply, #[CurrentUser] User $admin): JsonResponse
    {
        $id = $request->validated('targetId');

        // Filed under an organization (one the matching couldn't find), then read.
        if (is_string($id)) {
            $target = OutreachTarget::query()->findOrFail(Ids::decode($id) ?? 0);
            $reply->forceFill(['outreach_target_id' => $target->id])->save();

            if ($target->status !== 'do_not_contact') {
                $target->forceFill(['status' => 'replied'])->save();
            }

            AuditLog::record('outreach.reply_filed', $target, $admin, ip: $request->ip());
        }

        $reply->forceFill(['read_at' => $reply->read_at ?? now()])->save();

        return response()->json(['data' => OutreachResource::reply($reply->refresh())]);
    }

    public function suppress(SuppressOutreachRequest $request, #[CurrentUser] User $admin): Response
    {
        $email = strtolower((string) $request->validated('email'));
        $target = OutreachTarget::query()->where('contact_email', $email)->first();
        $note = $request->validated('note');
        OutreachSuppression::record($email, (string) ($request->validated('reason') ?? 'manual'), $target?->id, is_string($note) ? $note : null, $admin->id);
        $target?->forceFill(['status' => 'do_not_contact'])->save();
        AuditLog::record('outreach.suppressed', $target, $admin, after: ['reason' => $request->validated('reason') ?? 'manual'], ip: $request->ip());

        return response()->noContent();
    }
}
