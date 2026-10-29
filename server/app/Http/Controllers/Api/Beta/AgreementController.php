<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Http\Controllers\Controller;
use App\Support\Legal\LegalText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * beta.agreement.show: the beta agreement, as text blocks (never HTML).
 */
final class AgreementController extends Controller
{
    public function __invoke(Request $request, LegalText $legal): JsonResponse
    {
        $language = $request->query('lang');
        $text = $legal->read('beta-agreement', is_string($language) && preg_match('/^[a-z]{2}$/', $language) === 1 ? $language : 'en');

        // The agreement's own fields (a guide's description and song aren't part of it).
        unset($text['description'], $text['song']);

        return response()->json(['data' => $text]);
    }
}
