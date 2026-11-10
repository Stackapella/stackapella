{{-- "Learn it step by step" (docs/seo-lesson-pages-plan.md; marketing's map in Lessons::across): a guide's or a reference
     page's lessons to go on with, near the end of its teaching, before the onward links. One quiet list, each the
     lesson's name over its title (lang/en/lessons.php); nothing when none of its lessons has a page. --}}
@php($acrossLessons = \App\Support\Pages\Lessons::across($path))
@if ($acrossLessons !== [])
    {{-- Inside a guide's own column (`nested`) it takes that column's width; elsewhere a band of its own. --}}
    <nav class="{{ ($nested ?? false) ? 'mt-10' : 's-wrap s-sec2' }}" aria-labelledby="across-h" data-lessons-across>
        <h2 id="across-h" class="s-kick">{{ __('site.lessons.across') }}</h2>
        <ul class="s-cards s-tool-cards mt-4">
            @foreach ($acrossLessons as $acrossLesson)
                <li><a href="{{ $acrossLesson['url'] }}" class="s-card"><b>{{ $acrossLesson['name'] }}</b><span>{{ $acrossLesson['title'] }}</span></a></li>
            @endforeach
        </ul>
    </nav>
@endif
