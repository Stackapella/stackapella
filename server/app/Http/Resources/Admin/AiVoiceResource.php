<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\AiVoice;
use App\Models\TemplateGuide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An AI voice for Admin (contract/schemas/admin.aiVoice.json): who it's based on, their consent, and the live AI
 * guides it sings, which the withdraw modal names. Needs `guides.templateVersion.template` and `betaSinger` loaded.
 *
 * @property AiVoice $resource
 */
final class AiVoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $voice = $this->resource;

        return [
            'id' => $voice->sqid(),
            'name' => $voice->name,
            'basedOn' => $voice->based_on,
            'credited' => $voice->credited,
            'consent' => $voice->consent_date === null ? null : [
                'date' => $voice->consent_date->toDateString(),
                'version' => (string) $voice->consent_version,
                'paymentNoted' => $voice->payment_noted,
            ],
            // Licensed in the app by a beta singer (phase 1b): who, and which terms when.
            'singer' => $voice->betaSinger !== null ? ['id' => $voice->betaSinger->sqid(), 'name' => $voice->betaSinger->name] : null,
            'licence' => $voice->beta_singer_id !== null && $voice->accepted_at !== null ? ['version' => (int) $voice->consent_version, 'acceptedAt' => $voice->accepted_at->utc()->toIso8601ZuluString()] : null,
            'status' => $voice->isActive() ? 'active' : 'withdrawn',
            'withdrawnAt' => $voice->withdrawn_at?->utc()->toIso8601ZuluString(),
            'guides' => $voice->guides->map(static fn (TemplateGuide $guide): array => [
                'id' => $guide->sqid(),
                'templateId' => (string) $guide->templateVersion->template->slug,
                'title' => (string) $guide->templateVersion->template->title,
                'version' => $guide->templateVersion->version,
                'partKey' => $guide->part_key,
            ])->values()->all(),
        ];
    }
}
