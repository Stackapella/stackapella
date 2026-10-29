<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChoirFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One of our songs' virtual choir (N6): its voices, and the mix the server last made of them.
 *
 * @property int $id
 * @property int $template_id
 * @property string|null $mix_key
 * @property int $voices_count
 * @property Carbon|null $mixed_at
 * @property Carbon|null $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template $template
 */
#[UseFactory(ChoirFactory::class)]
final class Choir extends Model
{
    /** @use HasFactory<ChoirFactory> */
    use HasFactory;

    protected $fillable = ['template_id'];

    protected function casts(): array
    {
        return [
            'voices_count' => 'integer',
            'mixed_at' => 'datetime',
            'changed_at' => 'datetime',
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
     * @return HasMany<ChoirVoice, $this>
     */
    public function voices(): HasMany
    {
        return $this->hasMany(ChoirVoice::class);
    }
}
