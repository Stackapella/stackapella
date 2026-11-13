{{-- A song's story on its page (App\Support\Pages\SongStories; the courses lead's docs/songs/story-guide.md): its
     heading, a contents list of the sections it has, its lead picture when it has one (StoryFigures), the opening, its
     quote, the facts at a glance, then each section in the guide's order: the timeline under Where it comes from, Did
     you know as a list, the questions with their answers; then every source, numbered as the citations are, each its
     own id (source-N). Its "Sing next" is the page's More songs below. In the story's own language: an English story on a
     Spanish page says so with lang. --}}
@php($hero = \App\Support\Pages\StoryFigures::hero($story))
<section class="s-wrap s-sec2 s-sst" aria-labelledby="story-h" @if ($story['language'] !== app()->getLocale()) lang="{{ $story['language'] }}" @endif>
    <h2 id="story-h" class="s-h2">{{ __('pages.song.story.heading', ['song' => $song['title']]) }}</h2>
    <div class="s-sst-grid">
        <nav class="s-sst-toc" aria-labelledby="toc-h">
            <h3 id="toc-h" class="s-kick">{{ __('pages.song.story.contents') }}</h3>
            <ol>
                @foreach ($story['sections'] as $section)
                    @continue($section['key'] === 'next')
                    <li><a href="#{{ $section['id'] }}">{{ $section['heading'] }}</a></li>
                @endforeach
                <li><a href="#sources">{{ __('pages.song.story.sources') }}</a></li>
            </ol>
        </nav>
        <div class="s-sst-main">
            @if ($hero !== null)
                @include('site._story-figure', ['image' => $hero, 'lazy' => true])
            @endif
            <div class="s-prose s-sst-opening">{!! \App\Support\Pages\StoryFigures::fill($story['opening'], $story) !!}</div>
            @if ($story['quote'] !== null)
                <blockquote class="s-sst-quote">
                    <p>{{ $story['quote']['text'] }}</p>
                    @if ($quoteN !== null)
                        <footer><sup class="s-cite"><a href="#source-{{ $quoteN }}">[{{ $quoteN }}]</a></sup></footer>
                    @endif
                </blockquote>
            @endif
            {{-- The facts, and the kinds of song it is (moved from the hero's chips, the song page review). --}}
            @php($genres = $genres ?? [])
            @if (array_filter($story['facts']) !== [] || $genres !== [])
                <div class="s-sst-facts">
                    <h3 class="s-kick">{{ __('pages.song.story.facts') }}</h3>
                    <dl>
                        @foreach ($story['facts'] as $field => $value)
                            @if (is_string($value) && $value !== '')
                                <div><dt>{{ __('pages.song.story.fact.'.$field) }}</dt><dd>{{ $value }}@if ($field === 'earliestPrinting' && $story['earliestPrintingSource'] !== null)<sup class="s-cite"><a href="#source-{{ $story['earliestPrintingSource'] }}">[{{ $story['earliestPrintingSource'] }}]</a></sup>@endif</dd></div>
                            @endif
                        @endforeach
                        @if ($genres !== [])
                            <div><dt>{{ __('pages.song.story.fact.genre') }}</dt><dd>{{ implode(', ', $genres) }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            @foreach ($story['sections'] as $section)
                @continue($section['key'] === 'next')
                <section id="{{ $section['id'] }}" class="s-sst-sec" aria-labelledby="{{ $section['id'] }}-h">
                    <h3 id="{{ $section['id'] }}-h">{{ $section['heading'] }}</h3>
                    @if ($section['html'] !== '')
                        <div class="s-prose">{!! \App\Support\Pages\StoryFigures::fill($section['html'], $story) !!}</div>
                    @endif
                    @if ($section['key'] === 'origins' && $story['timeline'] !== [])
                        <h4 class="s-kick s-sst-tl-h">{{ __('pages.song.story.timeline') }}</h4>
                        <ol class="s-sst-tl">
                            @foreach ($story['timeline'] as $row)
                                <li><b class="s-xserif">{{ $row['year'] }}</b><span>{!! \App\Support\Pages\StoryFigures::fill($row['html'], $story) !!}</span></li>
                            @endforeach
                        </ol>
                    @endif
                    @if ($section['key'] === 'facts' && $story['didYouKnow'] !== [])
                        <ul class="s-sst-dyk">
                            @foreach ($story['didYouKnow'] as $fact)
                                <li>{!! $fact !!}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($section['key'] === 'questions' && $story['questions'] !== [])
                        <div class="s-sst-qs">
                            @foreach ($story['questions'] as $question)
                                <div class="s-sst-q">
                                    <h4>{{ $question['question'] }}</h4>
                                    <div class="s-prose">{!! $question['html'] !!}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach

            <section id="sources" class="s-sst-sec s-sst-sources" aria-labelledby="sources-h">
                <h3 id="sources-h">{{ __('pages.song.story.sources') }}</h3>
                @if ($story['checked'] !== null)
                    <p class="s-sst-checked">{{ __('pages.song.story.checked', ['count' => count($story['sources']), 'date' => \App\Support\Languages\Dates::dayMonthYear(\Illuminate\Support\Carbon::parse($story['checked']))]) }}</p>
                @endif
                <ol class="s-sst-src">
                    @foreach ($story['sources'] as $source)
                        <li id="source-{{ $source['n'] }}">{!! $source['html'] !!}@if ($source['url'] !== null) <a href="{{ $source['url'] }}" class="s-lk" rel="noopener">{{ __('pages.song.story.source_link') }}</a>@endif</li>
                    @endforeach
                </ol>
                @if (($story['listen'] ?? []) !== [])
                    <h4 class="s-kick">{{ __('pages.song.story.listen') }}</h4>
                    <ul class="s-sst-listen">
                        @foreach ($story['listen'] as $listen)
                            <li><a href="{{ $listen['url'] }}" class="s-lk" rel="noopener">{{ $listen['label'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</section>
