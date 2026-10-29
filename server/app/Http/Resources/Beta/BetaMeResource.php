<?php

declare(strict_types=1);

namespace App\Http\Resources\Beta;

use App\Models\BetaSinger;
use App\Support\Beta\TakeBar;
use App\Support\Gallery\Gallery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in account's singer record (contract/schemas/beta.me.json). Needs `user` loaded.
 *
 * @property BetaSinger $resource
 */
final class BetaMeResource extends JsonResource
{
    public function __construct(BetaSinger $singer, private readonly int $voiceOfParts)
    {
        parent::__construct($singer);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $singer = $this->resource;

        return [
            'id' => $singer->sqid(),
            'name' => $singer->name,
            'consent' => [
                'version' => $singer->consent_version,
                'acceptedAt' => $singer->consented_at?->utc()->toIso8601ZuluString(),
                'current' => (int) config('beta.agreement_version'),
            ],
            // The name is the account's (changed in the profile); null while anonymous.
            'credit' => ['mode' => $singer->credit_mode?->value, 'name' => $singer->credit()],
            // Linking the credit to their profile: their choice, and whether it can link now.
            'creditLinksProfile' => (bool) ($singer->getAttributes()['credit_links_profile'] ?? false),
            'hasPublicProfile' => $singer->user !== null && Gallery::hasPublicProfile($singer->user),
            'profilesOpen' => app(Gallery::class)->isOpen(),
            'voiceOfParts' => $this->voiceOfParts,
            // The bar a take must clear to be sent.
            'bar' => TakeBar::current(),
        ];
    }
}
