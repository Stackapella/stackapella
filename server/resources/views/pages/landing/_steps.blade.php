{{-- Four voices, four steps (Hillel's mockup, as a bento since the redesign: learn and share wide,
     a real voice and record narrow): learn (the real course map), a real voice, record, share, each
     card's picture drawn from the hero's song (Landing::pictures) when it's in the library. --}}
<section id="how" class="s-wrap s-sec" aria-labelledby="steps-h">
    {{-- On the How it works page itself, its eyebrow already says so. --}}
    @unless (request()->is('how-it-works'))
        <p class="s-kick">{{ __('pages.landing.steps_kick') }}</p>
    @endunless
    <h2 id="steps-h" class="s-title"><x-site.skin :game="__('pages.landing.game.steps_title')" :serious="__('pages.landing.steps_title')" /></h2>
    <p class="s-lede">{{ __('pages.landing.steps_lead') }}</p>
    <div class="s-steps s-bento">
        {{-- The first step shows the real app: the course map with a week under way (the guide's
             your-voice--map, light and dark, as the reader's theme), cropped to its top. --}}
        <article class="s-step s-v1">
            <p class="s-step-k"><span class="s-disc" aria-hidden="true">{{ mb_substr(__('pages.landing.hero.parts.soprano'), 0, 1) }}</span>{{ __('pages.landing.steps.learn.label') }}</p>
            <h3>{{ __('pages.landing.steps.learn.title') }}</h3>
            <p>{{ __('pages.landing.steps.learn.body') }}</p>
            <div class="s-vis s-vis-shot"><x-site.appshot id="your-voice--map" :alt="__('pages.landing.steps.learn.screens.course')" /></div>
        </article>
        <article class="s-step s-v2">
            <p class="s-step-k"><span class="s-disc" aria-hidden="true">{{ mb_substr(__('pages.landing.hero.parts.alto'), 0, 1) }}</span>{{ __('pages.landing.steps.voice.label') }}</p>
            <h3>{{ __('pages.landing.steps.voice.title') }}</h3>
            <p>{{ __('pages.landing.steps.voice.body') }}</p>
            <div class="s-vis" aria-hidden="true">
                <div class="s-seg"><span>{{ __('pages.landing.steps.voice.listen') }}</span><span class="s-on">{{ __('pages.landing.steps.voice.along') }}</span><span>{{ __('pages.landing.steps.voice.alone') }}</span></div>
                @if ($pictures['alto'] ?? null)
                    <div class="s-gv-row"><span>{{ __('pages.landing.steps.voice.singer') }}</span><div class="s-gv-wave">{{ $pictures['alto'] }}</div></div>
                    <div class="s-gv-row s-gv-you"><span>{{ __('pages.landing.steps.voice.you') }}</span><div class="s-gv-wave">{{ $pictures['alto'] }}</div></div>
                @endif
            </div>
        </article>
        <article class="s-step s-v3">
            <p class="s-step-k"><span class="s-disc" aria-hidden="true">{{ mb_substr(__('pages.landing.hero.parts.tenor'), 0, 1) }}</span>{{ __('pages.landing.steps.record.label') }}</p>
            <h3>{{ __('pages.landing.steps.record.title') }}</h3>
            <p>{{ __('pages.landing.steps.record.body') }}</p>
            <div class="s-vis" aria-hidden="true">
                @foreach ($pictures['stack'] ?? [] as $key => $picture)
                    <div class="s-trk s-v{{ $loop->iteration }}"><span @class(['s-trk-pick', 'gem' => $loop->last])></span><span class="s-trk-name"><span translate="no">{{ __('pages.landing.hero.parts.'.$key) }}</span></span><span class="s-wv" @if ($loop->last) style="clip-path: inset(0 45% 0 0)" @endif>{{ $picture }}</span></div>
                @endforeach
                <div class="s-vis-top" style="margin: 8px 0 0"><span class="s-recpill"><x-site.icon name="mic" size="13" /> {{ __('pages.landing.steps.record.recording') }}</span><span>{{ __('pages.landing.steps.record.checked') }}</span></div>
            </div>
        </article>
        <article class="s-step s-v4">
            <p class="s-step-k"><span class="s-disc" aria-hidden="true">{{ mb_substr(__('pages.landing.hero.parts.bass'), 0, 1) }}</span>{{ __('pages.landing.steps.share.label') }}</p>
            <h3>{{ __('pages.landing.steps.share.title') }}</h3>
            <p>{{ __('pages.landing.steps.share.body') }}</p>
            <div class="s-vis" aria-hidden="true">
                @if ($hero !== null)
                    <div class="s-vis-top"><b>{{ __('pages.landing.steps.share.every', ['song' => $hero['title']]) }}</b><span>{{ $hero['length'] }}</span></div>
                @endif
                @foreach ($pictures['whole'] ?? [] as $picture)
                    <div class="s-v{{ $loop->iteration }}" style="height: 14px">{{ $picture }}</div>
                @endforeach
                <div class="s-share-btns"><span class="btn-primary btn-sm"><x-site.icon name="link" size="15" /> {{ __('pages.landing.steps.share.link') }}</span><span class="btn-secondary btn-sm"><x-site.icon name="video" size="15" /> {{ __('pages.landing.steps.share.video') }}</span></div>
            </div>
        </article>
    </div>
    {{-- Through to the whole story on its own page (site/how-it-works), but not from that page itself. --}}
    @unless (request()->is('how-it-works'))
        <a href="/how-it-works" class="s-arrow">{{ __('pages.landing.steps_more') }} <x-site.icon name="arrow" size="16" /></a>
    @endunless
    {{-- Hillel: no app replaces a real vocal coach, said on the landing (CoachLineTest). --}}
    <p class="s-coachline">{{ __('pages.landing.coach_note') }}</p>
</section>
