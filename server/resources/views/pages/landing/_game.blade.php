{{-- The game skin's own sections (Hillel: "two skins for one app"; the arcade mockup v2): Stage
     select, the course's units as stages (web/course/course.json; 1 to 7 free, the rest Plus), and
     High scores, a part's leaderboard as the app shows it, with sample names and saying so. Hidden in
     the serious skin, which never talks stars, XP or boards (docs/skins-plan.md, 3). --}}
@php($coursePath = dirname(base_path()).'/web/course/course.json')
@php($units = is_file($coursePath) ? json_decode((string) file_get_contents($coursePath), true) : [])
@php($units = array_values((array) ($units['units'] ?? [])))
<section class="s-wrap s-sec s-skin-g" aria-labelledby="stage-h">
    <p class="s-kick">{{ __('pages.landing.game.stage_kick') }}</p>
    <h2 id="stage-h" class="s-title">{{ __('pages.landing.game.stage_title') }}</h2>
    <p class="s-lede">{{ __('pages.landing.game.stage_lead') }}</p>
    <ol class="s-stages">
        @foreach ($units as $unit)
            <li @class(['s-stage', 's-stage-plus' => $loop->iteration > 7])>
                <span class="s-stage-n">{{ __('pages.landing.game.stage', ['n' => $loop->iteration]) }}</span>
                <b>{{ $unit['title']['en'] ?? '' }}</b>
                <span class="s-stage-foot"><span>{{ trans_choice('pages.landing.game.lessons', count((array) ($unit['lessons'] ?? []))) }}</span><span @class(['s-tag', 's-tag-plus' => $loop->iteration > 7])>{{ $loop->iteration > 7 ? __('pages.landing.game.plus') : __('pages.landing.game.free') }}</span></span>
            </li>
        @endforeach
    </ol>
    <ul class="s-chips s-badges">
        @foreach ((array) \Illuminate\Support\Facades\Lang::get('pages.landing.game.badges') as $badge)
            <li><span class="s-v{{ $loop->iteration }}"><i class="gem" aria-hidden="true"></i>{{ $badge }}</span></li>
        @endforeach
    </ul>
</section>

<section class="s-wrap s-sec s-skin-g" aria-labelledby="scores-h">
    <div class="s-split">
        <div>
            <p class="s-kick">{{ __('pages.landing.game.scores_kick') }}</p>
            <h2 id="scores-h" class="s-title">{{ __('pages.landing.game.scores_title') }}</h2>
            <p class="s-lede">{{ __('pages.landing.game.scores_lead') }}</p>
            <ul class="s-ticks">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('pages.landing.game.scores_points') as $point)
                    <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                @endforeach
            </ul>
        </div>
        <figure class="s-cabinet s-board" aria-label="{{ __('pages.landing.game.sample') }}">
            <div class="s-cab-hud"><span>{{ __('pages.landing.game.scores_title') }}</span><span translate="no">{{ ($hero['title'] ?? '') }} · {{ __('pages.landing.hero.parts.alto') }}</span></div>
            <p class="s-board-tabs"><span class="s-on">{{ __('pages.landing.game.friends') }}</span><span>{{ __('pages.landing.game.everyone') }}</span></p>
            <ol class="s-board-rows">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('pages.landing.game.board') as [$rank, $name, $score])
                    <li @class(['s-you' => $loop->iteration === 2])><span>{{ $rank }}</span><b>{{ $name }}@if ($loop->iteration === 2) <i>{{ __('pages.landing.game.new_best') }}</i>@endif</b><span>{{ $score }}</span></li>
                @endforeach
            </ol>
            <figcaption class="s-board-foot"><span>{{ __('pages.landing.game.sample') }}</span><span class="btn-primary btn-sm">{{ __('pages.landing.game.challenge') }}</span></figcaption>
        </figure>
    </div>
</section>
