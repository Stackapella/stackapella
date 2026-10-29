<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Models\AuditLog;
use App\Models\BetaFeedback;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CourseClip;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Services\DigitalOcean\CdnPurge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removal on request (the beta agreement, "Removing your recordings", and their personal data):
 * every guide of theirs leaves the CDN, each part falls back to an alternate or the synth, their
 * masters, takes and notes go, and their name, email and credit are cleared. What was paid stays
 * as amounts and dates, for the accounts. It can't be undone.
 */
final class ForgetSinger
{
    public function __construct(private readonly ManageGuides $guides) {}

    public function handle(BetaSinger $singer, ?User $actor, ?string $ip = null): BetaSinger
    {
        $submissions = BetaSubmission::query()->whereIn('beta_assignment_id', $singer->assignments()->select('id'))->get();

        $masters = $submissions->pluck('master_key')->filter()->values()->all();

        // Forgotten first, under the singer's lock that a publish takes too: a guide being published
        // this moment either sees it and stops, or is done and goes with the rest below.
        DB::transaction(function () use ($singer, $submissions, $actor, $ip): void {
            BetaSinger::query()->whereKey($singer->id)->lockForUpdate()->first();

            foreach ($submissions as $submission) {
                $submission->forceFill(['master_key' => '', 'peaks' => null, 'scores' => null, 'device' => null, 'mic' => null, 'singer_note' => null, 'admin_note' => null])->saveQuietly();
            }

            BetaFeedback::query()->where('beta_singer_id', $singer->id)->delete();

            $singer->forceFill([
                'name' => '',
                'email' => null,
                'note' => null,
                'credit_mode' => null,
                'user_id' => null,
                'revoked_at' => $singer->revoked_at ?? now(),
                'forgotten_at' => now(),
            ])->save();

            AuditLog::record('beta.forgotten', $singer, $actor, after: ['takes' => $submissions->count()], ip: $ip);
        });

        foreach (TemplateGuide::query()->whereIn('beta_submission_id', $submissions->pluck('id'))->get() as $guide) {
            $this->guides->remove($guide, $actor, $ip, 'forgotten');
        }

        // Their course clips come down too: the step plays its fallback until someone records it again.
        foreach (CourseClip::query()->whereIn('beta_submission_id', $submissions->pluck('id'))->whereNotNull('media_key')->get() as $clip) {
            $media = (string) $clip->media_key;
            $clip->forceFill(['beta_submission_id' => null, 'media_key' => null, 'sample_rate' => null, 'frames' => null, 'published_at' => null])->save();
            app(CdnPurge::class)->forget([$media]);
            Storage::disk('media')->delete($media);
        }

        Storage::disk('private')->delete($masters);

        return $singer;
    }
}
