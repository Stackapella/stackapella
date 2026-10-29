{{-- For conductors and teachers: the tool under the game (the landing, and the directors' and
     coaches' for-pages). Serious skin: a calm editorial section. Game skin: the Intermission, where
     the screen powers down and the game says Paused, the same section in the game's dark (the site's
     game mode is always dark), then "Press start to continue", its words alone blinking (Hillel: "break out of the game for a bit, then fall back into it"). --}}
@php($coaching = app(\App\Support\Flags::class)->on('coach_signup') ? 'coaching_open' : 'coaching_soon')
<section class="s-teams" aria-labelledby="teams-h">
    <div class="s-teams-pause s-skin-g" aria-hidden="true"><span>{{ __('site.teams.paused') }}</span><b>{{ __('site.teams.intermission') }}</b></div>
    <div class="s-wrap s-teams-body">
        <div class="s-split">
            <div>
                <p class="s-teams-kick">{{ __('site.teams.kick') }}</p>
                <h2 id="teams-h" class="s-teams-title">{{ __('site.teams.title') }}</h2>
                <p class="s-teams-lead">{{ __('site.teams.lead') }}</p>
                <blockquote class="s-teams-quote">{{ __('site.teams.quote') }}</blockquote>
            </div>
            <div class="s-teams-shot"><x-site.appshot id="studio--mixer" :alt="__('site.teams.shot_alt')" /></div>
        </div>
        <ul class="s-teams-grid">
            @foreach ([...(array) \Illuminate\Support\Facades\Lang::get('site.teams.items'), (array) \Illuminate\Support\Facades\Lang::get('site.teams.'.$coaching)] as [$heading, $body])
                <li><b>{{ $heading }}</b><span>{{ $body }}</span></li>
            @endforeach
        </ul>
        <p class="s-actions-l s-teams-actions"><a href="/features/choirs" class="btn-primary btn-lg">{{ __('site.teams.groups') }}</a> <a href="/for/choir-directors" class="btn-secondary btn-lg">{{ __('site.teams.directors') }}</a></p>
    </div>
    <p class="s-teams-continue s-skin-g"><span>{{ __('site.teams.continue') }}</span></p>
</section>
