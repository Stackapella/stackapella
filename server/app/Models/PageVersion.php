<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public page's words as of its last change (app:sitemap:dates), for the sitemap's lastmod.
 *
 * @property int $id
 * @property string $path
 * @property string $content_hash
 * @property Carbon $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(PageVersionFactory::class)]
final class PageVersion extends Model
{
    /** @use HasFactory<PageVersionFactory> */
    use HasFactory;

    protected $fillable = ['path', 'content_hash', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
