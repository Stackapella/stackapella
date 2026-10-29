<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Jobs\PurgeCdn;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The person's picture: any JPEG, PNG or WebP made into a 256 px square JPEG with GD, at a new
 * immutable key on the CDN (the old one is deleted and purged).
 */
final class ChangeAvatar
{
    public const SIZE = 256;

    public function update(User $user, string $path): User
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            throw ValidationException::withMessages(['avatar' => __('validation.image', ['attribute' => 'avatar'])]);
        }

        $side = min(imagesx($image), imagesy($image));
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $image, 0, 0, (int) ((imagesx($image) - $side) / 2), (int) ((imagesy($image) - $side) / 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);
        $jpeg = (string) ob_get_clean();

        $key = 'avatars/'.Str::lower(Str::random(32)).'.jpg';
        Storage::disk('media')->put($key, $jpeg, ['visibility' => 'public', 'ContentType' => 'image/jpeg', 'CacheControl' => 'public, max-age=31536000, immutable']);

        $this->forget($user);
        $user->forceFill(['avatar_key' => $key])->save();

        return $user;
    }

    public function remove(User $user): User
    {
        $this->forget($user);
        $user->forceFill(['avatar_key' => null])->save();

        return $user;
    }

    private function forget(User $user): void
    {
        if ($user->avatar_key !== null) {
            Storage::disk('media')->delete($user->avatar_key);
            // Purged on the queue (it calls DigitalOcean's API; retried there).
            PurgeCdn::dispatch([$user->avatar_key]);
        }
    }
}
