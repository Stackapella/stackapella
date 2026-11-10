{{-- For conductors and teachers: the tool under the game (the landing, and the directors' and
     coaches' for-pages). Serious skin: a calm editorial section. Game skin: the Intermission, where
     the screen powers down and the game says Paused, the same section in the game's dark (the site's
     game mode is always dark), then "Press start to continue", its words alone blinking (Hillel: "break out of the game for a bit, then fall back into it").
     `landing` (landing v2, docs/landing-v2-plan.md): the buttons under the quote, Start a choir group into the app
     and For directors; a real director's group beside them (the light or the dark shot, as the theme); then the
     features and Also built for, the "for" pages as quiet chips. --}}
@php($coaching = app(\App\Support\Flags::class)->on('coach_signup') ? 'coaching_open' : 'coaching_soon')
@php($landing = $landing ?? false)
<section @class(['s-teams', 's-teams-landing' => $landing]) aria-labelledby="teams-h">
    <div class="s-teams-pause s-skin-g" aria-hidden="true"><span>{{ __('site.teams.paused') }}</span><b>{{ __('site.teams.intermission') }}</b></div>
    <div class="s-wrap s-teams-body">
        <div class="s-split">
            <div>
                <p class="s-teams-kick">{{ __('site.teams.kick') }}</p>
                <h2 id="teams-h" class="s-teams-title">{{ __('site.teams.title') }}</h2>
                <p class="s-teams-lead">{{ __('site.teams.lead') }}</p>
                <blockquote class="s-teams-quote">{{ __('site.teams.quote') }}</blockquote>
                @if ($landing)
                    <p class="s-actions-l s-teams-actions"><a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref=landing') }}" class="btn-primary btn-lg">{{ __('pages.site.group_start') }}</a> <a href="/for/choir-directors" class="btn-secondary btn-lg">{{ __('site.teams.directors') }}</a></p>
                @endif
            </div>
            @if ($landing)
                <figure class="s-teams-group">
                    <img class="s-light-only" src="/icons/landing/choir-group.webp" alt="{{ __('site.teams.group_alt') }}" width="1120" height="314" loading="lazy" decoding="async">
                    <img class="s-dark-only" src="/icons/landing/choir-group-dark.webp" alt="{{ __('site.teams.group_alt') }}" width="1120" height="314" loading="lazy" decoding="async">
                    <figcaption>{{ __('site.teams.group_caption') }}</figcaption>
                </figure>
            @else
                <div class="s-teams-shot"><x-site.appshot id="studio--mixer" :alt="__('site.teams.shot_alt')" /></div>
            @endif
        </div>
        <ul class="s-teams-grid">
            @foreach ([...(array) \Illuminate\Support\Facades\Lang::get('site.teams.items'), (array) \Illuminate\Support\Facades\Lang::get('site.teams.'.$coaching)] as [$heading, $body])
                <li><b>{{ $heading }}@if ($loop->last && $coaching === 'coaching_soon') <x-site.soon />@endif</b><span>{{ $body }}</span></li>
            @endforeach
        </ul>
        @if ($landing)
            @include('site._who', ['whoCompact' => true])
        @else
            <p class="s-actions-l s-teams-actions"><a href="/features/choirs" class="btn-primary btn-lg">{{ __('site.teams.groups') }}</a> <a href="/for/choir-directors" class="btn-secondary btn-lg">{{ __('site.teams.directors') }}</a></p>
        @endif
    </div>
    <p class="s-teams-continue s-skin-g"><span>{{ __('site.teams.continue') }}</span></p>
</section>
