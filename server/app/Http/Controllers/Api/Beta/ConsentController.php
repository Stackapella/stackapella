<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Beta;

use App\Actions\Beta\RecordConsent;
use App\Actions\Beta\UpdateCredit;
use App\Enums\CreditMode;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBetaSinger;
use App\Http\Requests\Beta\ConsentRequest;
use App\Http\Requests\Beta\UpdateCreditRequest;
use App\Http\Resources\Beta\BetaMeResource;
use App\Support\Beta\VoiceCount;

/**
 * beta.consent.create and beta.credit.update.
 */
final class ConsentController extends Controller
{
    public function store(ConsentRequest $request, RecordConsent $record): BetaMeResource
    {
        $singer = $record->handle(
            EnsureBetaSinger::singer($request),
            (int) $request->validated('version'),
            CreditMode::from((string) $request->validated('creditMode')),
        );

        return new BetaMeResource($singer, VoiceCount::of($singer));
    }

    public function update(UpdateCreditRequest $request, UpdateCredit $update): BetaMeResource
    {
        $singer = EnsureBetaSinger::singer($request);

        if ($singer->consented_at === null) {
            throw new Refusal(403, 'consent_required');
        }

        $mode = $request->validated('creditMode');
        $links = $request->validated('creditLinksProfile');
        $singer = $update->handle($singer, $mode !== null ? CreditMode::from((string) $mode) : null, $links !== null ? (bool) $links : null);

        return new BetaMeResource($singer, VoiceCount::of($singer));
    }
}
