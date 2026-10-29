<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\TemplateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One published version of a template: the JSON exactly as the app reads it. A version never
 * changes once published (docs/architecture.md, "Invariants"); the model refuses an edit.
 *
 * @property int $id
 * @property int $template_id
 * @property int $version
 * @property bool $reviewed
 * @property array{song: string, source: string|null, ownWork: bool, at: string}|null $declaration
 * @property array<string, mixed> $data
 * @property string $sha256
 * @property Carbon $created_at
 * @property-read Template $template
 */
#[UseFactory(TemplateVersionFactory::class)]
final class TemplateVersion extends Model
{
    /** @use HasFactory<TemplateVersionFactory> */
    use HasFactory;

    use HasSqid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'reviewed' => 'boolean',
            'declaration' => 'array',
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('A template version never changes once published; make a new version.'));
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
