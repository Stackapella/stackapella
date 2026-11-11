<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Models\Group;
use App\Models\User;
use App\Support\Flags;

/**
 * Who may make a song from sheet music (docs/sheet-music-plan.md; docs/score-import-plan.md, section 6): everyone
 * while `sheet_import` is `on`; only those who direct a choir group while it's `directors` (choir learning tracks,
 * stage 3); staff (admin, editors) always, to check it and to import a score sent with "Send us your song".
 */
final class SheetImport
{
    public function __construct(private readonly Flags $flags) {}

    public function allowed(?User $user): bool
    {
        return ($user !== null && ($user->is_admin || $user->is_editor)) || $this->open($user);
    }

    /**
     * The switch's answer for this person, staff aside (auth.config's `sheetImport`; staff see it through their
     * account).
     */
    public function open(?User $user): bool
    {
        return match ($this->flags->value('sheet_import')) {
            'on' => true,
            'directors' => $user !== null && Group::query()->where('director_id', $user->id)->exists(),
            default => false,
        };
    }
}
