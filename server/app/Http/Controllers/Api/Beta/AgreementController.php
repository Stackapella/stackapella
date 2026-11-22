<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AiVoices\AiLicence;
use App\Support\Legal\LegalText;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * beta.agreement.show: the beta agreement, as text blocks (never HTML).
 */
final class AgreementController extends Controller
{
    public function __invoke(Request $request, LegalText $legal): JsonResponse
    {
        // The AI voice licence is English only (Hillel, 2026-11-20: "just need english for accepting AI terms").
        $language = in_array($request->query('for'), ['ai_voice', 'own_choir'], true) ? 'en' : $request->query('lang');
        // The volunteers' own (Lend your voice), read before they join, the AI voice licence (phase 1b), or the beta singers'.
        $text = $legal->read(match ($request->query('for')) {
            'volunteer' => 'lend-your-voice',
            'ai_voice' => AiLicence::TEXT,
            'own_choir' => OwnChoir::TERMS,
            default => 'beta-agreement',
        }, is_string($language) && preg_match('/^[a-z]{2}$/', $language) === 1 ? $language : 'en');

        // The agreement's own fields (a guide's description and song aren't part of it).
        unset($text['description'], $text['song']);

        // "A draft a lawyer hasn't checked" is for staff only (2026-11-22): members never see the notice.
        $viewer = $request->user();
        $text = [...array_slice($text, 0, 2, true), 'draft' => ! $text['reviewed'] && $viewer instanceof User && ($viewer->is_admin || $viewer->is_editor), ...array_slice($text, 2, null, true)];

        return response()->json(['data' => $text]);
    }
}
