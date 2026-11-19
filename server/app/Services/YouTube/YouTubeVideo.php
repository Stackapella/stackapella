<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Enums\SongVideoHidden;

/**
 * What YouTube's videos.list says about one video, as far as YouTube sync needs it (docs/youtube-sync-plan.md). Held
 * no longer than the 30 days YouTube's Developer Policies allow.
 */
final class YouTubeVideo
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $channelTitle,
        public readonly int $durationSeconds,
        public readonly bool $embeddable,
        public readonly string $privacy,
        public readonly bool $madeForKids,
        public readonly bool $ageRestricted,
    ) {}

    /** Why we may not show it, or null when we may: public or unlisted, embeddable, not for kids, not age-restricted. */
    public function hiddenReason(): ?SongVideoHidden
    {
        return match (true) {
            ! in_array($this->privacy, ['public', 'unlisted'], true) => SongVideoHidden::Private,
            ! $this->embeddable => SongVideoHidden::NotEmbeddable,
            $this->madeForKids => SongVideoHidden::MadeForKids,
            $this->ageRestricted => SongVideoHidden::AgeRestricted,
            default => null,
        };
    }

    /**
     * @return array{id: string, title: string, channel: string, durationSeconds: int, usable: bool, hiddenReason: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'channel' => $this->channelTitle,
            'durationSeconds' => $this->durationSeconds,
            'usable' => $this->hiddenReason() === null,
            'hiddenReason' => $this->hiddenReason()?->value,
        ];
    }
}
