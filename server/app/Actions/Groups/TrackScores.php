<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Exceptions\Refusal;
use App\Models\Group;
use App\Models\SupportMessage;
use App\Models\TrackScore;
use App\Models\User;
use App\Support\Storage\ReceivedUploads;
use App\Support\Storage\Uploads;
use App\Support\Usage\UploadBytes;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The score's files a director sends with "Send us your song" (docs/choir-tracks-plan.md, Hillel
 * 2026-11-11): each a presigned PUT to the private bucket, as takes are (the day's upload cap counts
 * it), checked at complete by size, SHA-256 and its first bytes against the type it was sent as.
 * Only staff read them, through a 10-minute link. One never put in a request goes after a day.
 */
final class TrackScores
{
    /** The most one file may be (a phone photo or a long scanned PDF). */
    public const MAX_BYTES = 25 * 1024 * 1024;

    /** The most files one request takes (a photo a page). */
    public const MAX_FILES = 10;

    public function __construct(
        private readonly ManageGroups $groups,
        private readonly Uploads $uploads,
        private readonly UploadBytes $bytes,
        private readonly ReceivedUploads $received,
    ) {}

    /**
     * @param  array{name: string, contentType: string, bytes: int, sha256: string}  $data
     * @return array{score: TrackScore, upload: array{url: string, method: string, headers: array<string, string>, expiresAt: DateTimeInterface}}
     */
    public function start(User $user, string $groupId, array $data): array
    {
        $group = $this->groups->directed($user, $groupId);
        $this->bytes->count($user, $data['bytes']);

        $score = new TrackScore([
            'name' => self::name($data['name']),
            'kind' => TrackScore::KINDS[$data['contentType']],
            'content_type' => $data['contentType'],
            'bytes' => $data['bytes'],
            'sha256' => strtolower($data['sha256']),
        ]);
        $score->forceFill([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'status' => TrackScore::UPLOADING,
            'upload_key' => 'uploads/scores/'.Str::lower(Str::random(32)).'.'.TrackScore::EXTENSIONS[$data['contentType']],
        ])->save();

        return ['score' => $score, 'upload' => $this->uploads->presign($score->upload_key, $score->content_type, $score->bytes, minutes: 30)];
    }

    public function complete(User $user, string $groupId, string $scoreId): TrackScore
    {
        $group = $this->groups->directed($user, $groupId);
        $score = TrackScore::query()->whereSqid($scoreId)->where('user_id', $user->id)->where('group_id', $group->id)->first() ?? throw new Refusal(404, 'not_found');

        if ($score->status === TrackScore::READY) {
            return $score;
        }

        $copy = $this->received->receive($score->upload_key, $score->bytes, $score->sha256, 'scores');

        // Another complete is checking it this moment: as it is.
        if ($copy === null) {
            return $score->refresh();
        }

        $head = self::head($copy);

        if (! self::looksLike($score->kind, $score->content_type, $head)) {
            Storage::disk('private')->delete($copy);
            throw new Refusal(422, 'score_type');
        }

        if (TrackScore::query()->whereKey($score->id)->where('status', TrackScore::UPLOADING)->update(['status' => TrackScore::READY, 'file_key' => $copy, 'updated_at' => now()]) !== 1) {
            Storage::disk('private')->delete($copy);
        }

        return $score->refresh();
    }

    /**
     * Puts the files into the request: each must be this director's, for this group, ready, and in
     * no request yet.
     *
     * @param  list<string>  $scoreIds
     */
    public function attach(User $user, Group $group, array $scoreIds, SupportMessage $message): int
    {
        if ($scoreIds === []) {
            return 0;
        }

        $scores = TrackScore::query()->where('user_id', $user->id)->where('group_id', $group->id)->whereNull('support_message_id')->where('status', TrackScore::READY)->lockForUpdate()->get()
            ->filter(static fn (TrackScore $score): bool => in_array($score->sqid(), $scoreIds, true));

        if ($scores->count() !== count(array_unique($scoreIds))) {
            throw ValidationException::withMessages(['scoreIds' => __('validation.track_scores')]);
        }

        TrackScore::query()->whereKey($scores->modelKeys())->update(['support_message_id' => $message->id, 'updated_at' => now()]);

        return $scores->count();
    }

    /**
     * What staff see of a request's files; `$links` adds a 10-minute download link to each.
     *
     * @return list<array{id: string, name: string, kind: string, bytes: int, url: string|null}>
     */
    public static function forMessage(SupportMessage $message, bool $links): array
    {
        return array_values(TrackScore::query()->where('support_message_id', $message->id)->where('status', TrackScore::READY)->orderBy('id')->get()
            ->map(static fn (TrackScore $score): array => [
                'id' => $score->sqid(),
                'name' => $score->name,
                'kind' => $score->kind,
                'bytes' => $score->bytes,
                'url' => $links && $score->file_key !== null ? Storage::disk('private')->temporaryUrl($score->file_key, now()->addMinutes(10), ['ResponseContentDisposition' => 'attachment; filename="'.self::asciiName($score).'"']) : null,
            ])->all());
    }

    /**
     * Files never put in a request (or whose request has gone) a day on: deleted, rows and files.
     */
    public function prune(): int
    {
        $pruned = 0;

        TrackScore::query()->whereNull('support_message_id')->where('created_at', '<', now()->subDay())->chunkById(200, function ($scores) use (&$pruned): void {
            Storage::disk('private')->delete($scores->flatMap(static fn (TrackScore $score): array => array_values(array_filter([$score->upload_key, $score->file_key])))->all());
            TrackScore::query()->whereKey($scores->modelKeys())->delete();
            $pruned += $scores->count();
        });

        return $pruned;
    }

    /**
     * Every file key a person's scores hold (DeleteAccount deletes the files; the rows go by the
     * foreign key).
     *
     * @return list<string>
     */
    public static function keysOf(User $user): array
    {
        return array_values(TrackScore::query()->where('user_id', $user->id)->get(['upload_key', 'file_key'])
            ->flatMap(static fn (TrackScore $score): array => array_values(array_filter([$score->upload_key, $score->file_key])))->all());
    }

    /**
     * Whether a file's first bytes are what it was sent as.
     */
    public static function looksLike(string $kind, string $contentType, string $head): bool
    {
        return match ($kind) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'mxl' => str_starts_with($head, "PK\x03\x04"),
            'musicxml' => self::isMusicXml($head),
            'photo' => match ($contentType) {
                'image/jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
                'image/png' => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
                'image/heic' => substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1'], true),
                default => false,
            },
            default => false,
        };
    }

    private static function isMusicXml(string $head): bool
    {
        // A BOM and whitespace may come first; the root is score-partwise or score-timewise, after
        // the XML declaration and the DOCTYPE when there are.
        $text = ltrim(str_starts_with($head, "\xEF\xBB\xBF") ? substr($head, 3) : $head);

        return str_starts_with($text, '<') && preg_match('/<(?:!DOCTYPE\s+)?score-(?:partwise|timewise)\b/', $text) === 1;
    }

    /** The first 4 KB, enough for any type's mark and MusicXML's root. */
    private static function head(string $key): string
    {
        $stream = Storage::disk('private')->readStream($key);

        if (! is_resource($stream)) {
            return '';
        }

        try {
            return (string) fread($stream, 4096);
        } finally {
            fclose($stream);
        }
    }

    /** The name as staff see it: plain text, no folders, at most 200 characters. */
    private static function name(string $name): string
    {
        $base = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', $name))));

        return mb_substr($base !== '' ? $base : 'score', 0, 200);
    }

    private static function asciiName(TrackScore $score): string
    {
        $ascii = trim((string) preg_replace('/[^A-Za-z0-9 ._-]+/', '', Str::ascii(pathinfo($score->name, PATHINFO_FILENAME))));

        return ($ascii !== '' ? mb_substr($ascii, 0, 80) : 'score').'.'.(TrackScore::EXTENSIONS[$score->content_type] ?? 'bin');
    }
}
