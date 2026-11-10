{{-- Every lesson page of a course (docs/seo-lesson-pages-plan.md), linked from its course page so a reader and Google
     reach each one: by unit, each page's name and its title (marketing's, lang/en/lessons.php). A <nav>, so it's a
     landmark below a designed page's own <main>. Nothing when the course has no lesson pages (not open). --}}
@php($lessonPages = \App\Support\Pages\Lessons::pages($course))
@if ($lessonPages !== [])
    <nav class="s-wrap s-sec2" aria-labelledby="lesson-pages-h" data-lesson-pages>
        <h2 id="lesson-pages-h" class="s-kick">{{ __('site.lessons.every') }}</h2>
        @if ($lessonPages[0]['kind'] === 'unit')
            <ul class="s-cards s-tool-cards mt-4">
                @foreach ($lessonPages as $lessonPage)
                    <li><a href="{{ $lessonPage['url'] }}" class="s-card"><b>{{ __('site.lessons.unitOf', ['number' => $lessonPage['unitNumber'], 'unit' => $lessonPage['name']]) }}</b><span>{{ $lessonPage['title'] }}</span></a></li>
                @endforeach
            </ul>
        @else
            @foreach (collect($lessonPages)->groupBy('unitNumber') as $number => $unitPages)
                <h3 class="mt-6 font-bold">{{ __('site.lessons.unitOf', ['number' => $number, 'unit' => $unitPages[0]['unit']]) }}</h3>
                <ul class="s-cards s-tool-cards mt-3">
                    @foreach ($unitPages as $lessonPage)
                        <li><a href="{{ $lessonPage['url'] }}" class="s-card"><b>{{ $lessonPage['name'] }}</b><span>{{ $lessonPage['title'] }}</span></a></li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </nav>
@endif
