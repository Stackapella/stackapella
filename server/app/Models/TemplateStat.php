<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TemplateStatFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A song's nightly counts for What's next (docs/more-features-plan.md 13): how many people have
 * finished it, and the songs those people finished most besides. From our own records only.
 *
 * @property int $id
 * @property int $template_id
 * @property int $finishers
 * @property list<int> $also_learned
 * @property Carbon $computed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template $template
 */
#[UseFactory(TemplateStatFactory::class)]
final class TemplateStat extends Model
{
    /** @use HasFactory<TemplateStatFactory> */
    use HasFactory;

    protected $fillable = ['template_id', 'finishers', 'also_learned', 'computed_at'];

    protected function casts(): array
    {
        return [
            'finishers' => 'integer',
            'also_learned' => 'array',
            'computed_at' => 'datetime',
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
