<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DropoffReportFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One week's drop-off report (App\Actions\Growth\Dropoffs): anonymous totals and pass-through, by path and step.
 *
 * @property int $id
 * @property Carbon $week_end the last day of the seven (report timezone)
 * @property array<string, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(DropoffReportFactory::class)]
final class DropoffReport extends Model
{
    /** @use HasFactory<DropoffReportFactory> */
    use HasFactory;

    protected $fillable = ['week_end', 'data'];

    protected function casts(): array
    {
        return [
            'week_end' => 'date',
            'data' => 'array',
        ];
    }
}
