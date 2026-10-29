{{-- A coach's picture, or their initial in a circle when they have none. --}}
@if ($coach['avatarUrl'] !== null)
    <img src="{{ $coach['avatarUrl'] }}" alt="" width="{{ $size }}" height="{{ $size }}" class="s-avatar" loading="lazy">
@else
    <span class="s-avatar s-avatar-initial s-v2" aria-hidden="true">{{ mb_substr((string) $coach['name'], 0, 1) }}</span>
@endif
