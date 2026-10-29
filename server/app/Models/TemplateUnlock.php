<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TemplateUnlockFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A song kept by someone who started a project from it while it was the song of the month.
 *
 * @property int $id
 * @property int $user_id
 * @property int $template_id
 * @property Carbon|null $created_at
 */
#[UseFactory(TemplateUnlockFactory::class)]
final class TemplateUnlock extends Model
{
    /** @use HasFactory<TemplateUnlockFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'template_id',
    ];
}
