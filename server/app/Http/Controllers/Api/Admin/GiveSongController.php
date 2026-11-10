<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Templates\OwnTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GiveSongRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * admin.people.songs.create: a partner's song into the account (docs/partner-songs.md, gap 1),
 * imported and private, with its rights record. In sudo; audited.
 */
final class GiveSongController extends Controller
{
    public function __invoke(GiveSongRequest $request, User $person, OwnTemplates $templates, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var array<string, mixed> $song */
        $song = (array) $request->validated('song');
        /** @var array{confirmedBy: string, confirmedAt: string, wording: string, source: string} $rights */
        $rights = (array) $request->validated('rights');
        $template = $templates->give($person, $song, $rights, $admin, $request->ip());

        return response()->json(['data' => [
            'id' => $template->slug,
            'title' => $template->title,
            'version' => (int) $template->currentVersion?->version,
            'owner' => ['id' => $person->sqid(), 'name' => $person->name],
            'visibility' => $template->visibility,
            'imported' => true,
            'rights' => [
                'confirmedBy' => $template->rights_confirmed_by,
                'confirmedAt' => $template->rights_confirmed_at?->utc()->toIso8601ZuluString(),
                'source' => $template->rights_source,
            ],
        ]], 201);
    }
}
