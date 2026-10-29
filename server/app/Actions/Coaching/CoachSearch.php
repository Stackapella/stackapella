<?php

declare(strict_types=1);

namespace App\Actions\Coaching;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\Template;
use App\Models\User;
use App\Support\Coaching\CoachingView;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The coach directory (N4; docs/coaching-marketplace-plan.md, F7; contract/api.md, "The coach
 * directory (N4)"): finding a coach without their link, behind the `coach_directory` flag. Only
 * approved coaches who can take payment are listed. Prices, turnaround and the next lesson are
 * worked out hourly (refreshAll).
 */
final class CoachSearch
{
    public const PER_PAGE = 24;

    /** How far ahead the next lesson is looked for. */
    private const LESSON_DAYS = 14;

    public function __construct(private readonly Flags $flags, private readonly Gallery $gallery, private readonly Lessons $lessons) {}

    public function isOpen(): bool
    {
        return $this->flags->on('coach_directory');
    }

    /**
     * Coaches anyone can find: approved, able to take payment, not suspended, not blocked with the
     * caller.
     *
     * @return Builder<CoachProfile>
     */
    public function listed(?User $viewer = null): Builder
    {
        $blocked = $viewer !== null ? $this->gallery->blockedIds($viewer) : [];

        return CoachProfile::query()->where('status', CoachProfile::APPROVED)->where('charges_enabled', true)
            ->whereHas('user', fn ($user) => $user->whereNull('suspended_at')->whereNotNull('handle')->where('hide_profile', false))
            ->when($blocked !== [], fn ($query) => $query->whereNotIn('user_id', $blocked));
    }

    /**
     * coaches.list.
     *
     * @param  array{voiceType?: string|null, style?: string|null, language?: string|null, maxPrice?: int|string|null, currency?: string|null, turnaround?: int|string|null, lessons?: bool|string|null, rating?: int|string|null, song?: string|null, sort?: string|null, tab?: string|null, cursor?: string|null}  $filters
     * @return LengthAwarePaginator<int, CoachProfile>
     */
    public function search(?User $viewer, array $filters): LengthAwarePaginator
    {
        if (! $this->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        $voice = $filters['voiceType'] ?? null;
        $style = $filters['style'] ?? null;
        $language = $filters['language'] ?? null;
        $maxPrice = $filters['maxPrice'] ?? null;
        $currency = strtolower((string) ($filters['currency'] ?? ''));
        $turnaround = $filters['turnaround'] ?? null;
        $rating = $filters['rating'] ?? null;
        $minRatings = (int) config('coaching.ratings_to_show');

        $query = $this->listed($viewer)->with('user')
            ->when($voice !== null, fn ($q) => $q->whereJsonContains('voice_types', $voice))
            ->when($style !== null, fn ($q) => $q->whereJsonContains('styles', $style))
            ->when($language !== null, fn ($q) => $q->whereJsonContains('languages', $language))
            ->when($maxPrice !== null && $currency !== '', fn ($q) => $q->where('from_currency', $currency)->where('from_cents', '<=', (int) $maxPrice))
            ->when($turnaround !== null, fn ($q) => $q->whereNotNull('turnaround_days')->where('turnaround_days', '<=', (int) $turnaround))
            ->when(in_array($filters['lessons'] ?? null, [true, '1', 1, 'true'], true), fn ($q) => $q->whereNotNull('next_lesson_at')->where('next_lesson_at', '>', now()))
            ->when($rating !== null, fn ($q) => $q->where('rating_count', '>=', $minRatings)->where('rating_average', '>=', (float) $rating));

        if (($filters['tab'] ?? null) === 'featured') {
            $query->whereNotNull('featured_at')->orderByDesc('featured_at');
        } else {
            match ($filters['sort'] ?? 'suggested') {
                'rating' => $query->orderByRaw('rating_count >= ? desc', [$minRatings])->orderByDesc('rating_average')->orderByDesc('rating_count'),
                'price' => $query->orderByRaw('from_cents is null')->orderBy('from_cents'),
                'soonest' => $query->orderByRaw('next_lesson_at is null')->orderBy('next_lesson_at'),
                default => $this->suggested($query, $voice, self::genres($filters['song'] ?? null), $minRatings),
            };
        }

        $page = max(1, (int) ($filters['cursor'] ?? 1));

        return $query->orderBy('id')->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * Featured first, then those who teach the singer's voice type, then whose styles match the
     * song's genres, then by rating.
     *
     * @param  Builder<CoachProfile>  $query
     * @param  list<string>  $genres
     */
    private function suggested(Builder $query, ?string $voice, array $genres, int $minRatings): void
    {
        $query->orderByRaw('featured_at is null');

        if ($voice !== null) {
            $query->orderByRaw('json_contains(voice_types, ?) desc', [json_encode($voice)]);
        }

        if ($genres !== []) {
            $query->orderByRaw('('.implode(' + ', array_fill(0, count($genres), 'json_contains(cast(lower(styles) as json), ?)')).') desc', array_map(static fn (string $g): string => (string) json_encode($g), $genres));
        }

        $query->orderByRaw('rating_count >= ? desc', [$minRatings])->orderByDesc('rating_average');
    }

    /**
     * A song's genres, for suggestions.
     *
     * @return list<string>
     */
    private static function genres(?string $song): array
    {
        if ($song === null) {
            return [];
        }

        $template = Template::query()->with('currentVersion')->where('slug', $song)->whereNull('deleted_at')->first();

        return array_values(array_filter(array_map(static fn (mixed $g): string => is_string($g) ? mb_strtolower($g) : '', (array) ($template?->currentVersion->data['genre'] ?? []))));
    }

    /**
     * The styles and languages listed coaches have, for the filters.
     *
     * @return array{styles: list<string>, languages: list<string>}
     */
    public function facets(?User $viewer): array
    {
        $styles = [];
        $languages = [];

        foreach ($this->listed($viewer)->get(['styles', 'languages']) as $profile) {
            foreach ($profile->styles as $style) {
                $styles[$style] = true;
            }

            foreach ($profile->languages as $language) {
                $languages[$language] = true;
            }
        }

        $styles = array_map('strval', array_keys($styles));
        $languages = array_map('strval', array_keys($languages));
        sort($styles);
        sort($languages);

        return ['styles' => $styles, 'languages' => $languages];
    }

    /**
     * A coach as the directory shows them.
     *
     * @return array<string, mixed>
     */
    public static function item(CoachProfile $profile): array
    {
        $summary = CoachingView::ratingSummary($profile);

        return [
            'handle' => (string) $profile->user->handle,
            'name' => $profile->user->name,
            'avatarUrl' => CoachingView::profile($profile)['avatarUrl'],
            'bio' => self::short($profile->bio),
            'voiceTypes' => $profile->voice_types,
            'styles' => $profile->styles,
            'languages' => $profile->languages,
            'timezone' => $profile->timezone,
            'rating' => $summary,
            'newCoach' => $summary['average'] === null,
            'from' => $profile->from_cents !== null && $profile->from_currency !== null ? ['cents' => $profile->from_cents, 'currency' => $profile->from_currency] : null,
            'turnaroundDays' => $profile->turnaround_days,
            'nextLessonAt' => $profile->next_lesson_at !== null && $profile->next_lesson_at->isFuture() ? $profile->next_lesson_at->utc()->toIso8601ZuluString() : null,
            'featured' => $profile->featured_at !== null,
        ];
    }

    public function feature(string $handle, User $admin, bool $featured, ?string $ip): CoachProfile
    {
        $profile = CoachProfile::query()->with('user')->where('status', CoachProfile::APPROVED)->whereHas('user', fn ($user) => $user->where('handle', $handle))->first() ?? throw new Refusal(404, 'not_found');
        $profile->forceFill(['featured_at' => $featured ? ($profile->featured_at ?? now()) : null])->save();
        AuditLog::record($featured ? 'coaches.featured' : 'coaches.unfeatured', $profile->user, $admin, ip: $ip);

        return $profile;
    }

    /**
     * The hourly refresh: each approved coach's lowest price, fastest review and next free lesson
     * time. A coach whose Event Schedule can't be reached keeps no next lesson until it can.
     */
    public function refreshAll(): int
    {
        $count = 0;

        CoachProfile::query()->where('status', CoachProfile::APPROVED)->chunkById(50, function ($profiles) use (&$count): void {
            foreach ($profiles as $profile) {
                $this->refresh($profile);
                $count++;
            }
        });

        return $count;
    }

    public function refresh(CoachProfile $profile): void
    {
        $offerings = CoachOffering::query()->where('coach_id', $profile->user_id)->whereNull('archived_at')->get()
            ->filter(fn (CoachOffering $o): bool => $o->kind !== CoachOffering::LESSON || Lessons::connectionFor($o) !== null);
        // The lowest price in the currency most of their offerings use.
        $currency = $offerings->countBy('currency')->sortDesc()->keys()->first();
        $cheapest = $offerings->where('currency', $currency)->sortBy('price_cents')->first();
        $reviews = $offerings->whereIn('kind', [CoachOffering::REVIEW, CoachOffering::PACKAGE]);
        $next = null;

        foreach ($offerings->where('kind', CoachOffering::LESSON) as $lesson) {
            try {
                $first = $this->lessons->slots($lesson, null, self::LESSON_DAYS, 'UTC')['slots'][0]['startsAt'] ?? null;
            } catch (Refusal) {
                // Not taking payment yet, or its Event Schedule isn't connected: no lessons to sell.
                $first = null;
            } catch (Throwable $e) {
                report($e);
                $first = null;
            }

            if (is_string($first) && ($next === null || $first < $next)) {
                $next = $first;
            }
        }

        DB::table('coach_profiles')->where('id', $profile->id)->update([
            'from_cents' => $cheapest?->price_cents,
            'from_currency' => $cheapest !== null ? strtolower($cheapest->currency) : null,
            'turnaround_days' => $reviews->min('turnaround_days'),
            'next_lesson_at' => $next !== null ? now()->parse($next) : null,
            'directory_refreshed_at' => now(),
        ]);
    }

    private static function short(string $bio): string
    {
        $bio = trim($bio);

        return mb_strlen($bio) <= 200 ? $bio : rtrim(Str::beforeLast(mb_substr($bio, 0, 200), ' '), ' ,.;:').'...';
    }
}
