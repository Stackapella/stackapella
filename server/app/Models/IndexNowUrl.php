<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IndexNowUrlFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public page we told IndexNow about, and the lastmod it had then (App\Actions\Site\SubmitToIndexNow).
 *
 * @property int $id
 * @property string $path_hash
 * @property string $path
 * @property Carbon|null $lastmod
 * @property Carbon $submitted_at
 */
#[UseFactory(IndexNowUrlFactory::class)]
final class IndexNowUrl extends Model
{
    /** @use HasFactory<IndexNowUrlFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'indexnow_urls';

    protected $fillable = ['path_hash', 'path', 'lastmod', 'submitted_at'];

    public static function hashOf(string $path): string
    {
        return hash('sha256', $path);
    }

    protected function casts(): array
    {
        return [
            'lastmod' => 'date',
            'submitted_at' => 'datetime',
        ];
    }
}
