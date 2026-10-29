<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The official templates in the catalogue, a reviewed Amazing Grace, and a singer asked for its alto.
 */
trait BetaFixtures
{
    protected function catalogue(bool $reviewed = true): Template
    {
        app(SyncOfficialTemplates::class)->handle();
        $template = Template::query()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail();

        // A test-only shortcut: in real life a version's reviewed comes from its file.
        DB::table('template_versions')->where('id', $template->current_version_id)->update(['reviewed' => $reviewed]);
        $template->load('currentVersion');

        return $template;
    }

    /**
     * @return array{0: BetaSinger, 1: BetaAssignment}
     */
    protected function singerWithAlto(bool $consented = true): array
    {
        $template = Template::query()->where('slug', 'amazing-grace')->firstOrFail();
        $singer = $consented ? BetaSinger::factory()->accepted()->consented()->create() : BetaSinger::factory()->accepted()->create();
        $assignment = BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'template_id' => $template->id, 'part_key' => 'alto']);

        return [$singer, $assignment];
    }

    /**
     * Signed in as the singer's own account (one is made for a singer who hasn't accepted yet).
     */
    protected function asSinger(BetaSinger $singer): static
    {
        if ($singer->user_id === null) {
            $singer->forceFill(['user_id' => User::factory()->create(['name' => 'Sarah K.'])->id, 'accepted_at' => now()])->save();
        }

        $user = User::query()->findOrFail($singer->user_id);

        return $this->actingAs($user);
    }
}
