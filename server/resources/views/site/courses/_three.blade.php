{{-- Who the course is for, what you need, singing it safely, and the songs with their credits, side by
     side. --}}
<section class="s-wrap s-sec cp-three" aria-label="{{ $page['titles']['who'] }}">
    <div>
        <h2 class="s-kick">{{ $page['titles']['who'] }}</h2>
        <ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
    </div>
    <div>
        <h2 class="s-kick">{{ $page['titles']['need'] }}</h2>
        <ul class="s-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
    </div>
    <div>
        <h2 class="s-kick">{{ $page['titles']['safety'] }}</h2>
        <ul class="s-ticks">@foreach ((array) $page['safety'] as $line)<li><x-site.icon name="info" size="18" />{{ $line }}</li>@endforeach</ul>
    </div>
    @if (($page['songs'] ?? []) !== [])
        <div>
            <h2 class="s-kick">{{ __('pages.site.course_songs') }}</h2>
            <ul class="s-course-songs">
                @foreach ((array) $page['songs'] as [$song, $credit])
                    <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
