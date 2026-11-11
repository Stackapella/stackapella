<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\TrackScoreFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One file of a score sent with a learning tracks request (App\Actions\Groups\TrackScores): private,
 * sent before the request and attached to it, deleted after a day if it never is.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $group_id
 * @property int|null $support_message_id
 * @property string $name
 * @property string $kind
 * @property string $content_type
 * @property int $bytes
 * @property string $sha256
 * @property string $status
 * @property string $upload_key
 * @property string|null $file_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read SupportMessage|null $message
 */
#[UseFactory(TrackScoreFactory::class)]
final class TrackScore extends Model
{
    /** @use HasFactory<TrackScoreFactory> */
    use HasFactory;

    use HasSqid;

    public const UPLOADING = 'uploading';

    public const READY = 'ready';

    /** What a score may be, by the type it's sent as: a PDF, MusicXML (plain or compressed), or a photo of a page. */
    public const KINDS = [
        'application/pdf' => 'pdf',
        'application/vnd.recordare.musicxml+xml' => 'musicxml',
        'application/vnd.recordare.musicxml' => 'mxl',
        'image/jpeg' => 'photo',
        'image/png' => 'photo',
        'image/heic' => 'photo',
    ];

    /** The extension each type is kept under. */
    public const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'application/vnd.recordare.musicxml+xml' => 'musicxml',
        'application/vnd.recordare.musicxml' => 'mxl',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/heic' => 'heic',
    ];

    protected $fillable = ['name', 'kind', 'content_type', 'bytes', 'sha256'];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SupportMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }
}
