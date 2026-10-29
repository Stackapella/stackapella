<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TemplatePartRangeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One part's range in a template's current version (MIDI notes), for the library's "fits my
 * voice" filter (N3). Rewritten with each version.
 *
 * @property int $id
 * @property int $template_id
 * @property string $part_key
 * @property int $low
 * @property int $high
 * @property-read Template $template
 */
#[UseFactory(TemplatePartRangeFactory::class)]
final class TemplatePartRange extends Model
{
    /** @use HasFactory<TemplatePartRangeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'low' => 'integer',
            'high' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
