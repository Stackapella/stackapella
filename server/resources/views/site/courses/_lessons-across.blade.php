{{-- "Learn it step by step" (docs/seo-lesson-pages-plan.md; marketing's map in Lessons::across): a guide's or a reference
     page's lessons to go on with, near the end of its teaching, before the onward links. One quiet list, each the
     lesson's name over its title (lang/en/lessons.php); nothing when none of its lessons has a page. --}}
@php($acrossLessons = \App\Support\Pages\Lessons::across($path))
@if ($acrossLessons !== [])
    {{-- Inside a guide's own column (`nested`) it takes that column's width; elsewhere a band of its own. --}}
    <nav class="{{ ($nested ?? false) ? 'mt-10' : 's-wrap s-sec2' }}" aria-labelledby="across-h" data-lessons-across>
        <h2 id="across-h" class="s-kick">{{ __('site.lessons.across') }}</h2>
        {{-- `stacked`: one card a row, each with an arrow, for a page's narrower column (Hillel's width design, 2026-11-11). --}}
        <ul @class(['mt-4', 's-cards s-tool-cards' => ! ($stacked ?? false), 's-across-stack s-tool-cards' => $stacked ?? false])>
            @foreach ($acrossLessons as $acrossLesson)
                <li><a href="{{ $acrossLesson['url'] }}" class="s-card"><b>{{ $acrossLesson['name'] }}</b><span>{{ $acrossLesson['title'] }}</span>@if ($stacked ?? false)<x-site.icon name="arrow" size="16" />@endif</a></li>
            @endforeach
        </ul>
    </nav>
@endif
