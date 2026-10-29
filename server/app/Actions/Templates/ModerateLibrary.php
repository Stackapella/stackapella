<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\Notices\Notices;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Notice;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hillel's side of the template library (N3): approve one waiting for review, hide one (its
 * owner gets a notice they can appeal; projects made from it keep their copy), show it again,
 * and the Featured shelf. Every action is audited.
 */
final class ModerateLibrary
{
    public function __construct(private readonly Notices $notices, private readonly Library $library) {}

    public function decide(Template $template, User $admin, string $decision, string $note, ?string $ip): void
    {
        DB::transaction(function () use ($template, $admin, $decision, $note, $ip): void {
            $locked = Template::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            match ($decision) {
                'approve' => $this->approve($locked, $admin, $note, $ip),
                'hide' => $this->hide($locked, $admin, $note, $ip),
                default => $this->show($locked, $admin, $note, $ip),
            };
        });
    }

    public function feature(Template $template, User $admin, bool $featured, ?string $ip): void
    {
        DB::transaction(function () use ($template, $admin, $featured, $ip): void {
            // Under the lock unpublishing takes, so it can't leave the shelf meanwhile.
            $locked = Template::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            if ($featured && ! $this->library->isListed($locked)) {
                throw new Refusal(409, 'not_listed');
            }

            $locked->timestamps = false;
            $locked->forceFill(['featured_at' => $featured ? ($locked->featured_at ?? now()) : null])->save();
            AuditLog::record($featured ? 'library.featured' : 'library.unfeatured', $locked, $admin, ip: $ip);
        });
    }

    /**
     * An appeal won (Notices): the template shows again.
     */
    public function restore(Template $template, User $admin, string $note, ?string $ip): void
    {
        $this->decide($template, $admin, 'show', $note, $ip);
    }

    private function approve(Template $template, User $admin, string $note, ?string $ip): void
    {
        if ($template->held_at === null || $template->hidden_at !== null || ! $template->isPublic()) {
            throw new Refusal(409, 'not_held');
        }

        $template->timestamps = false;
        $template->forceFill(['held_at' => null])->save();
        $template->timestamps = true;
        $this->answerReports($template, $admin, Report::DISMISSED);
        AuditLog::record('library.approved', $template, $admin, reason: $note !== '' ? $note : null, ip: $ip);
    }

    private function hide(Template $template, User $admin, string $note, ?string $ip): void
    {
        if ($template->hidden_at !== null) {
            return;
        }

        $copyright = Report::query()->whereMorphedTo('reportable', $template)->where('status', Report::OPEN)->where('reason', 'copyright')->exists();
        $template->timestamps = false;
        $template->forceFill(['hidden_at' => now(), 'held_at' => null, 'featured_at' => null])->save();
        $template->timestamps = true;
        $this->answerReports($template, $admin, Report::UPHELD);
        AuditLog::record('library.hidden', $template, $admin, reason: $note, ip: $ip);

        if ($template->owner !== null) {
            $this->notices->give($template->owner, Notice::TEMPLATE_HIDDEN, $template, $copyright ? 'copyright' : 'community_guidelines', $note);
        }
    }

    private function show(Template $template, User $admin, string $note, ?string $ip): void
    {
        if ($template->hidden_at === null) {
            throw new Refusal(409, 'not_held');
        }

        $template->timestamps = false;
        $template->forceFill(['hidden_at' => null])->save();
        $template->timestamps = true;
        AuditLog::record('library.shown', $template, $admin, reason: $note !== '' ? $note : null, ip: $ip);
    }

    private function answerReports(Template $template, User $admin, string $status): void
    {
        Report::query()->whereMorphedTo('reportable', $template)->where('status', Report::OPEN)->update(['status' => $status, 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);
    }
}
