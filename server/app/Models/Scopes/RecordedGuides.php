<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\GuideSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Every guide query sees recorded voices only, unless it asks for AI ones by name (TemplateGuide::withAi()): so
 * hasRealGuide, Be the voice's wanted parts, the voice counts, XP, showreels, shares and carrying voices to a new
 * version count real singers alone without each having to remember (docs/ai-voices-build-plan.md, backend item 5).
 *
 * @implements Scope<Model>
 */
final class RecordedGuides implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('source'), GuideSource::Recorded->value);
    }
}
