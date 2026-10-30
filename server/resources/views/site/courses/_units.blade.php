{{-- A designed course page's units and lessons (site/courses/<slug>), from the course's own file
     (SitePages::courseOutline), each unit with its line in plain words (the page's `units_say`): Stage
     n in the game skin, Unit n in the serious, the first lesson marked Free. --}}
<ol class="cp-units">
    @foreach ($outline['units'] as $unit)
        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
        <li class="cp-unit">
            <p class="cp-unit-n"><x-site.skin :game="$stage" :serious="$unitName" /></p>
            <h3>{{ $unit['title'] }}</h3>
            @isset($page['units_say'][$loop->index])
                <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
            @endisset
            <ol class="cp-lessons">
                @foreach ($unit['lessons'] as $lesson)
                    <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                @endforeach
            </ol>
        </li>
    @endforeach
</ol>
