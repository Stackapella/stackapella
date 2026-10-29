<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TemplateRatingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's stars for a public template (N3): only from someone who made a project from it,
 * one each, changeable.
 *
 * @property int $id
 * @property int $template_id
 * @property int $user_id
 * @property int $stars
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template $template
 * @property-read User $user
 */
#[UseFactory(TemplateRatingFactory::class)]
final class TemplateRating extends Model
{
    /** @use HasFactory<TemplateRatingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
