{{-- /help, the user guide's front (docs/user-guide-plan.md), in the redesign's sections: search with
     what comes up most as chips under it, the "Start here" path, every other group as an even tile of
     its pages (none listed twice), and Still stuck on the band with Contact us in the app. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

{{-- Calm: people read here (the arcade-versus-calm rule). --}}
@section('calm', true)

@section('head')
    @include('pages._head')
@endsection

@section('content')
    @include('pages._header')
    <main>
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('pages.help.eyebrow') }}</p>
            <h1 id="page-h">{{ __('pages.help.headline') }}</h1>
            <p class="s-lead">{{ __('pages.help.lead') }}</p>
            <div class="s-help-find">
                @include('pages.help._search', ['id' => 'help-search', 'hero' => true])
            </div>
        </section>

        {{-- What comes up most, as chips under the search. --}}
        @if ($most !== [])
            <section class="s-wrap" aria-labelledby="most-h">
                <h2 id="most-h" class="s-kick" style="margin-top: 22px">{{ __('pages.help.most') }}</h2>
                <ul class="s-chips" style="margin-top: 12px">
                    @foreach ($most as $page)
                        <li><a href="/help/{{ $page['slug'] }}"><span class="s-who-ic s-v{{ $loop->iteration }}" aria-hidden="true"><x-site.icon :name="$page['icon']" size="16" /></span>{{ $page['title'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($path !== [])
            <section class="s-wrap s-sec" aria-labelledby="path-h">
                <p class="s-kick">{{ __('pages.help.path') }}</p>
                <h2 id="path-h" class="s-rowtitle" style="margin-top: 10px">{{ __('pages.help.path_lead') }}</h2>
                <ol class="s-helppath">
                    @foreach ($path as $page)
                        <li>
                            <a href="/help/{{ $page['slug'] }}">
                                <i class="gem s-v{{ $loop->iteration }}" aria-hidden="true">{{ $loop->iteration }}</i>
                                <b>{{ $page['title'] }}</b>
                                <span>{{ $page['summary'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- Every other page, in its group: an even tile of titles to scan. --}}
        @php($onPath = collect($path)->pluck('slug')->all())
        <section class="s-wrap s-sec" aria-label="{{ __('pages.help.contents') }}">
            <div class="s-helptiles">
                @foreach ($groups as $group)
                    @continue($group['key'] === 'start')
                    <div class="s-card3d">
                        <h2 class="s-helptile-h"><span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon :name="\App\Support\Site\HelpGuide::GROUP_ICONS[$group['key']] ?? 'spark'" size="16" /></span>{{ $group['title'] }}</h2>
                        <ul>
                            @foreach ($group['pages'] as $page)
                                @continue(in_array($page['slug'], $onPath, true))
                                <li><a href="/help/{{ $page['slug'] }}">{{ $page['title'] }} <x-site.icon name="chevron-end" size="16" /></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="s-ink" aria-labelledby="stuck-h">
            <div class="s-wrap s-final">
                <h2 id="stuck-h">{{ __('pages.help.stuck') }}</h2>
                <p>{{ __('pages.help.stuck_body', ['email' => config('site.contact_email')]) }}</p>
                <p class="s-final-btns"><a href="{{ $contactUrl }}" class="btn-primary btn-lg">{{ __('pages.help.contact_link') }}</a></p>
            </div>
        </section>
    </main>
    @include('pages._footer')
    <script type="module" src="/js/public/help-search.js"></script>
@endsection
