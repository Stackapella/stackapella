<?php

declare(strict_types=1);

namespace App\Actions\Coaching;

use App\Exceptions\Refusal;
use App\Jobs\ProcessReviewMedia;
use App\Models\CoachOffering;
use App\Models\Engagement;
use App\Models\LessonBooking;
use App\Models\Part;
use App\Models\ReviewItem;
use App\Models\ReviewMedia;
use App\Models\Take;
use App\Models\User;
use App\Support\Storage\ReceivedUploads;
use App\Support\Storage\Uploads;
use App\Support\Usage\UploadBytes;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The review workspace (docs/coaching-marketplace-plan.md F4): what the singer sends (the server's
 * own copy of a take, its pitch track and scores, a question), the coach's draft, delivering with
 * a 10-minute Unsend, one follow-up question and its answer, and the coach's voice notes and demos.
 */
final class Reviews
{
    public function __construct(private readonly Uploads $uploads, private readonly UploadBytes $bytes) {}

    /**
     * Send another take: one of a package's month, to a teacher, or before a lesson.
     *
     * @param  array{takeId?: string|null, question?: string|null, pitchTrack?: array<string, mixed>|null, scores?: array<string, mixed>|null}  $data
     */
    public function create(User $singer, string $engagementSqid, array $data): ReviewItem
    {
        return DB::transaction(function () use ($singer, $engagementSqid, $data): ReviewItem {
            $engagement = Engagement::query()->whereSqid($engagementSqid)->where('singer_id', $singer->id)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

            $lesson = $engagement->kind === Engagement::LESSON;
            $open = $lesson
                ? in_array($engagement->status, [Engagement::REQUESTED, Engagement::BOOKED], true) && LessonBooking::query()->where('engagement_id', $engagement->id)->where('starts_at', '>', now())->exists()
                : in_array($engagement->kind, [Engagement::PACKAGE, Engagement::TEACHER], true) && in_array($engagement->status, [Engagement::ACTIVE, Engagement::ENDING], true);

            if (! $open) {
                throw new Refusal(409, 'engagement_state');
            }

            // Before a lesson: "what I want to work on", one take (coaching.lesson_takes).
            if ($lesson && ReviewItem::query()->where('engagement_id', $engagement->id)->count() >= (int) config('coaching.lesson_takes')) {
                throw new Refusal(409, 'reviews_used');
            }

            if ($engagement->kind === Engagement::PACKAGE) {
                if ((int) $engagement->reviews_left < 1) {
                    throw new Refusal(409, 'reviews_used');
                }

                $engagement->forceFill(['reviews_left' => (int) $engagement->reviews_left - 1])->save();
            }

            $review = $this->attach($singer, $engagement, $data, $engagement->offering_id !== null ? CoachOffering::query()->whereKey($engagement->offering_id)->value('max_seconds') : null);
            $review->forceFill(['status' => ReviewItem::ACCEPTED, 'due_at' => $engagement->turnaround_days !== null ? now()->addDays($engagement->turnaround_days) : null])->save();

            return $review;
        });
    }

    /**
     * The take, its pitch track and scores, kept as the server's own copy.
     *
     * @param  array{takeId?: string|null, question?: string|null, pitchTrack?: array<string, mixed>|null, scores?: array<string, mixed>|null}  $data
     */
    public function attach(User $singer, Engagement $engagement, array $data, ?int $maxSeconds): ReviewItem
    {
        // Only the singer's own voice: a take they recorded (in their project or someone else's), never
        // a collaborator's on their project, and not one in the bin.
        $take = Take::query()->where('uuid', (string) ($data['takeId'] ?? ''))
            ->where(fn ($query) => $query->where('recorded_by', $singer->id)->orWhere(fn ($own) => $own->whereNull('recorded_by')->where('user_id', $singer->id)))
            ->where('status', Take::READY)->whereNull('binned_at')->first();

        if ($take === null || $take->file_key === null) {
            throw ValidationException::withMessages(['takeId' => __('validation.review_take')]);
        }

        if ($maxSeconds !== null && $take->frames > $maxSeconds * $take->sample_rate) {
            throw ValidationException::withMessages(['takeId' => __('validation.review_length', ['seconds' => $maxSeconds])]);
        }

        $pitch = isset($data['pitchTrack']) ? (string) json_encode($data['pitchTrack']) : null;

        if ($pitch !== null && strlen($pitch) > (int) config('coaching.pitch_track_bytes')) {
            throw ValidationException::withMessages(['pitchTrack' => __('validation.settings_size', ['kb' => intdiv((int) config('coaching.pitch_track_bytes'), 1024)])]);
        }

        $base = 'reviews/'.Str::lower(Str::random(32));
        Storage::disk('private')->copy($take->file_key, $base.'.flac');

        if ($pitch !== null) {
            Storage::disk('private')->put($base.'.json', $pitch);
        }

        $part = $take->part_id !== null ? Part::query()->with('project')->find($take->part_id) : null;
        $review = new ReviewItem([
            'question' => trim((string) ($data['question'] ?? '')),
            'template_slug' => $part?->project->template_slug,
            'template_version' => $part?->project->template_version,
            'part_key' => $part?->template_part_key,
            'scores' => $data['scores'] ?? null,
        ]);
        $review->forceFill([
            'engagement_id' => $engagement->id,
            'status' => ReviewItem::REQUESTED,
            'take_key' => $base.'.flac',
            'sample_rate' => $take->sample_rate,
            'frames' => $take->frames,
            'pitch_track_key' => $pitch !== null ? $base.'.json' : null,
        ])->save();

        return $review;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function save(User $coach, string $sqid, array $draft): ReviewItem
    {
        $review = $this->coachs($coach, $sqid);

        if ($review->status !== ReviewItem::ACCEPTED) {
            throw new Refusal(409, 'engagement_state');
        }

        $review->forceFill(['draft' => self::clean($draft)])->save();

        return $review;
    }

    public function deliver(User $coach, string $sqid): ReviewItem
    {
        return DB::transaction(function () use ($coach, $sqid): ReviewItem {
            $review = $this->locked($coach, $sqid);

            // Too late is too late: past its turnaround, a paid review is refunded, not delivered.
            if ($review->status !== ReviewItem::ACCEPTED || ($review->engagement->kind === Engagement::REVIEW && ($review->engagement->status !== Engagement::ACCEPTED || $review->engagement->due_at?->isPast()))) {
                throw new Refusal(409, 'engagement_state');
            }

            if (($review->draft['summary'] ?? null) === null) {
                throw ValidationException::withMessages(['summary' => __('validation.review_summary')]);
            }

            $review->forceFill(['status' => ReviewItem::DELIVERED, 'delivered' => $review->draft, 'delivered_at' => now()])->save();

            if ($review->engagement->kind === Engagement::REVIEW) {
                $review->engagement->forceFill(['status' => Engagement::DELIVERED, 'ended_at' => now()])->save();
            }

            return $review;
        });
    }

    public function unsend(User $coach, string $sqid): ReviewItem
    {
        return DB::transaction(function () use ($coach, $sqid): ReviewItem {
            $review = $this->locked($coach, $sqid);

            if ($review->status !== ReviewItem::DELIVERED || $review->released_at !== null || $review->delivered_at?->lt(now()->subMinutes((int) config('coaching.unsend_minutes')))) {
                throw new Refusal(409, 'unsend_window');
            }

            $review->forceFill(['status' => ReviewItem::ACCEPTED, 'delivered' => null, 'delivered_at' => null])->save();

            if ($review->engagement->kind === Engagement::REVIEW) {
                $review->engagement->forceFill(['status' => Engagement::ACCEPTED, 'ended_at' => null])->save();
            }

            return $review;
        });
    }

    public function followUp(User $singer, string $sqid, string $question): ReviewItem
    {
        $review = ReviewItem::query()->with('engagement')->whereSqid($sqid)->whereHas('engagement', fn ($query) => $query->where('singer_id', $singer->id))->first() ?? throw new Refusal(404, 'not_found');

        if ($review->status !== ReviewItem::DELIVERED || $review->follow_up_question !== null) {
            throw new Refusal(409, 'engagement_state');
        }

        $review->forceFill(['follow_up_question' => trim($question), 'follow_up_at' => now()])->save();

        return $review;
    }

    public function answer(User $coach, string $sqid, string $text, ?string $mediaId): ReviewItem
    {
        $review = $this->coachs($coach, $sqid);

        if ($review->follow_up_question === null || $review->follow_up_answer !== null) {
            throw new Refusal(409, 'engagement_state');
        }

        $media = $mediaId !== null ? ReviewMedia::query()->where('uuid', $mediaId)->where('review_item_id', $review->id)->value('uuid') : null;
        $review->forceFill(['follow_up_answer' => trim($text), 'follow_up_media' => $media, 'follow_up_answered_at' => now()])->save();

        return $review;
    }

    /**
     * @param  array{id: string, kind: string, sampleRate: int, frames: int, bytes: int, sha256: string}  $data
     * @return array{media: ReviewMedia, upload: array{url: string, method: string, headers: array<string, string>, expiresAt: DateTimeInterface}|null}
     */
    public function startMedia(User $coach, string $sqid, array $data): array
    {
        $review = $this->coachs($coach, $sqid);
        $limit = $data['kind'] === ReviewMedia::VOICE_NOTE ? 120 : (int) config('usage.take_max_seconds');

        if ($data['frames'] > $limit * $data['sampleRate']) {
            throw ValidationException::withMessages(['frames' => __('validation.review_length', ['seconds' => $limit])]);
        }

        $media = ReviewMedia::query()->where('uuid', $data['id'])->first();

        if ($media !== null && ($media->review_item_id !== $review->id || $media->sha256 !== strtolower($data['sha256']))) {
            throw ValidationException::withMessages(['id' => __('validation.take_id')]);
        }

        if ($media === null) {
            // The same daily cap on uploaded bytes as a take.
            $this->bytes->count($coach, $data['bytes']);
            $media = new ReviewMedia(['kind' => $data['kind'], 'sample_rate' => $data['sampleRate'], 'frames' => $data['frames'], 'upload_bytes' => $data['bytes'], 'sha256' => strtolower($data['sha256'])]);
            $media->forceFill(['uuid' => $data['id'], 'review_item_id' => $review->id, 'user_id' => $coach->id, 'status' => ReviewMedia::UPLOADING, 'upload_key' => 'uploads/reviews/'.Str::lower(Str::random(32)).'.wav'])->save();
        }

        return ['media' => $media, 'upload' => $media->status === ReviewMedia::UPLOADING ? $this->uploads->presign($media->upload_key, 'audio/wav', $media->upload_bytes, minutes: 10) : null];
    }

    public function completeMedia(User $coach, string $uuid): ReviewMedia
    {
        $media = ReviewMedia::query()->where('uuid', $uuid)->where('user_id', $coach->id)->first() ?? throw new Refusal(404, 'not_found');

        if ($media->status !== ReviewMedia::UPLOADING) {
            return $media;
        }

        $received = app(ReceivedUploads::class)->receive($media->upload_key, $media->upload_bytes, $media->sha256);

        // Another complete is checking it this moment: as it is.
        if ($received === null) {
            return $media->refresh();
        }

        if (ReviewMedia::query()->whereKey($media->id)->where('status', ReviewMedia::UPLOADING)->update(['status' => ReviewMedia::PROCESSING, 'upload_key' => $received, 'updated_at' => now()]) === 1) {
            ProcessReviewMedia::dispatch($media->id);
        } else {
            Storage::disk('private')->delete($received);
        }

        return $media->refresh();
    }

    /**
     * A review's data as it's kept: only the fields the workspace uses, within their sizes, as
     * plain text.
     *
     * @param  array<string, mixed>  $data
     * @return array{comments: list<array{id: string, atSeconds: float, noteIndex: int|null, text: string|null, mediaId: string|null}>, summary: array{working: string, toWorkOn: list<string>, practise: string}|null, exercises: list<array{packId: string|null, exerciseId: string}>, demoMediaId: string|null}
     */
    public static function clean(array $data): array
    {
        $text = static fn (mixed $value, int $max): ?string => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
        $comments = [];

        foreach (array_slice(array_values(array_filter((array) ($data['comments'] ?? []), 'is_array')), 0, 100) as $comment) {
            $comments[] = [
                // The device's id for it (so it can be edited, and an answer can point at it), or one made here.
                'id' => is_string($comment['id'] ?? null) && Str::isUuid($comment['id']) ? $comment['id'] : (string) Str::uuid(),
                'atSeconds' => round(max(0, (float) ($comment['atSeconds'] ?? 0)), 3),
                'noteIndex' => is_int($comment['noteIndex'] ?? null) ? $comment['noteIndex'] : null,
                'text' => $text($comment['text'] ?? null, 1000),
                'mediaId' => is_string($comment['mediaId'] ?? null) && Str::isUuid($comment['mediaId']) ? $comment['mediaId'] : null,
            ];
        }

        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : null;

        return [
            'comments' => $comments,
            'summary' => $summary === null ? null : [
                'working' => (string) $text($summary['working'] ?? null, 1000),
                'toWorkOn' => array_values(array_filter(array_map(static fn (mixed $point): ?string => $text($point, 300), array_slice((array) ($summary['toWorkOn'] ?? []), 0, 3)))),
                'practise' => (string) $text($summary['practise'] ?? null, 1000),
            ],
            // Each a reference: an exercise of the app's own library (packId null) or of a pack.
            'exercises' => array_values(array_filter(array_map(static fn (mixed $ref): ?array => is_array($ref) && ($id = $text($ref['exerciseId'] ?? null, 80)) !== null ? ['packId' => is_string($ref['packId'] ?? null) && Str::isUuid($ref['packId']) ? $ref['packId'] : null, 'exerciseId' => $id] : null, array_slice((array) ($data['exercises'] ?? []), 0, 20)))),
            'demoMediaId' => is_string($data['demoMediaId'] ?? null) && Str::isUuid($data['demoMediaId']) ? $data['demoMediaId'] : null,
        ];
    }

    /**
     * The review and its engagement, locked in the order every money change takes (the engagement
     * first, as refunds and releases do).
     */
    private function locked(User $coach, string $sqid): ReviewItem
    {
        $found = $this->coachs($coach, $sqid);
        $engagement = Engagement::query()->whereKey($found->engagement_id)->lockForUpdate()->firstOrFail();
        $review = ReviewItem::query()->whereKey($found->id)->lockForUpdate()->firstOrFail();

        return $review->setRelation('engagement', $engagement);
    }

    /**
     * A review on one of the coach's live engagements (the teacher's too).
     */
    private function coachs(User $coach, string $sqid, bool $lock = false): ReviewItem
    {
        $review = ReviewItem::query()->with('engagement')->whereSqid($sqid)->whereHas('engagement', fn ($query) => $query->where('coach_id', $coach->id))->when($lock, fn ($query) => $query->lockForUpdate())->first();

        if ($review === null || ! Engagements::coachMaySee($review->engagement)) {
            throw new Refusal(404, 'not_found');
        }

        return $review;
    }
}
