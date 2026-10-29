<?php

declare(strict_types=1);

namespace App\Services\Social;

/**
 * What one post says (a port of iaw's App\Data\SocialMessage): its text, the link (Bluesky's
 * card; X gets none), and an image with its alt text, or the harmony's own share video (an MP4,
 * with its width and height) in its place.
 */
final readonly class SocialMessage
{
    public function __construct(
        public string $text,
        public string $url,
        public string $title,
        public string $description,
        public ?string $image = null,
        public string $imageAlt = '',
        public ?string $video = null,
        public int $videoWidth = 0,
        public int $videoHeight = 0,
    ) {}
}
