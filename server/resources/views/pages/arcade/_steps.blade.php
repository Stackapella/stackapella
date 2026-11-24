{{-- Four voices, four steps, as one stepper (Hillel's landing redesign, 2026-11-15): the four steps on the left, the chosen
     one open with its text, and one panel on the right showing that step, with the four voices as a bar under it, filled up
     to the step. The steps are radios with :has (as the plans' Yearly and Monthly), so it works without a script, the arrow
     keys move between them, nothing moves by itself, and each step's text describes its radio. On a phone the panel sits
     under the chosen step (order). The pictures are drawn from the hero's song (Landing::pictures) when it's in the
     library; the learn step is the real course map. Then the coach note as one quiet bar. The How it works page shows it
     too. --}}
@php($stepKeys = ['learn', 'voice', 'record', 'share'])
<section id="how" class="s-wrap s-sec" aria-labelledby="steps-h">
    {{-- On the How it works page itself, its eyebrow already says so. --}}
    @unless (request()->is('how-it-works'))
        <p class="s-kick">{{ __('pages.landing.steps_kick') }}</p>
    @endunless
    <div class="s-headsplit">
        <h2 id="steps-h" class="s-title"><x-site.skin heading :game="__('pages.landing.game.steps_title')" :serious="__('pages.landing.steps_title')" /></h2>
        <p class="s-lede">{{ __('pages.landing.steps_lead') }}</p>
    </div>
    <fieldset class="s-lr-steps" data-ui="steps">
        <legend class="sr-only">{{ __('pages.landing.steps_pick') }}</legend>
        @foreach ($stepKeys as $i => $key)
            @php($n = $i + 1)
            <div class="s-lr-step s-v{{ $n }}" style="--n: {{ $n }}">
                <label class="s-lr-pick">
                    <input type="radio" name="lr-step" id="lr-step-{{ $n }}" value="{{ $n }}" class="sr-only" aria-describedby="lr-step-{{ $n }}-body" @checked($n === 1)>
                    <span class="s-disc gem" aria-hidden="true"><x-site.skin :game="__('site.game.level', ['n' => $n])" :serious="(string) $n" /></span>
                    <span class="s-lr-words">
                        <span class="s-lr-k">{{ __('pages.landing.steps.'.$key.'.label') }}</span>
                        <span class="s-lr-t">{{ __('pages.landing.steps.'.$key.'.title') }}</span>
                    </span>
                </label>
                <p class="s-lr-body" id="lr-step-{{ $n }}-body">{{ __('pages.landing.steps.'.$key.'.body') }}</p>
            </div>
        @endforeach
        <div class="s-lr-panel">
            <div class="s-lr-card">
                {{-- 1, learn: the course's home as the app draws it, in live words (Hillel, 2026-11-15: not the screenshot, so
                     calm shows no pink and Spanish reads Spanish): Learn, Sing harmony, your voice, then the next unit's first
                     lesson with the tune's notes lighting up as they're sung. --}}
                @php($learnUnit = \App\Support\Pages\Lessons::pages(\App\Support\Pages\Lessons::FREE)[1] ?? null)
                @php($learnTune = collect(\App\Support\Site\Pictures::parts($hero ?? null))->first())
                <div class="s-lr-pane s-lr-learn" data-step="1" aria-hidden="true">
                    <div class="s-lr-learn-top">
                        <p><span>{{ \App\Support\Site\AppWords::get('course.hub.eyebrow') }}</span><b>{{ \App\Support\Site\AppWords::get('course.hub.title') }}</b></p>
                        <span class="s-lr-chip"><i></i>{{ \App\Support\Site\AppWords::fill('course.hub.voiceIs', ['voice' => __('pages.landing.hero.parts.alto')]) }}</span>
                    </div>
                    @if ($learnUnit !== null)
                        <div class="s-lr-learn-next">
                            <p class="s-lr-learn-k">{{ \App\Support\Site\AppWords::fill('course.hub.upNextUnit', ['number' => $learnUnit['unitNumber']]) }}</p>
                            <p class="s-lr-learn-t">{{ $learnUnit['lessons'][0]['name'] ?? $learnUnit['name'] }}</p>
                            @if ($learnTune !== null)
                                <div class="s-lr-learn-roll s-v{{ $learnTune['color'] }}"><span class="s-lr-dim">{{ \App\Support\Site\Pictures::roll($hero, $learnTune, 56) }}</span><span class="s-lr-lit">{{ \App\Support\Site\Pictures::roll($hero, $learnTune, 56) }}</span></div>
                            @endif
                        </div>
                    @endif
                </div>
                {{-- 2, a real voice: the singer's wave over yours, Sing along chosen. --}}
                <div class="s-lr-pane s-v2" data-step="2" aria-hidden="true">
                    <div class="s-seg"><span>{{ __('pages.landing.steps.voice.listen') }}</span><span class="s-on">{{ __('pages.landing.steps.voice.along') }}</span><span>{{ __('pages.landing.steps.voice.alone') }}</span></div>
                    @if ($pictures['alto'] ?? null)
                        <div class="s-gv-row"><span>{{ __('pages.landing.steps.voice.singer') }}</span><div class="s-gv-wave">{{ $pictures['alto'] }}</div></div>
                        <div class="s-gv-row s-gv-you"><span>{{ __('pages.landing.steps.voice.you') }}</span><div class="s-gv-wave">{{ $pictures['alto'] }}</div></div>
                    @endif
                </div>
                {{-- 3, record: the parts so far and the tenor going down, the timing checked. --}}
                <div class="s-lr-pane" data-step="3" aria-hidden="true">
                    @foreach ($pictures['stack'] ?? [] as $part => $picture)
                        <div class="s-trk s-v{{ $loop->iteration }}"><span @class(['s-trk-pick', 'gem' => $loop->last])></span><span class="s-trk-name"><span translate="no">{{ __('pages.landing.hero.parts.'.$part) }}</span></span><span class="s-wv" @if ($loop->last) style="clip-path: inset(0 45% 0 0)" @endif>{{ $picture }}</span></div>
                    @endforeach
                    <div class="s-vis-top s-lr-rec"><span class="s-recpill"><x-site.icon name="mic" size="13" /> {{ __('pages.landing.steps.record.recording') }}</span><span>{{ __('pages.landing.steps.record.checked') }}</span></div>
                </div>
                {{-- 4, share: the whole song stacked, a link or a video. --}}
                <div class="s-lr-pane" data-step="4" aria-hidden="true">
                    @if ($hero !== null)
                        <div class="s-vis-top"><b>{{ __('pages.landing.steps.share.every', ['song' => $hero['title']]) }}</b><span>{{ $hero['length'] }}</span></div>
                    @endif
                    @foreach ($pictures['whole'] ?? [] as $picture)
                        <div class="s-v{{ $loop->iteration }} s-lr-whole">{{ $picture }}</div>
                    @endforeach
                    <div class="s-share-btns"><span class="btn-primary btn-sm"><x-site.icon name="link" size="15" /> {{ __('pages.landing.steps.share.link') }}</span><span class="btn-secondary btn-sm"><x-site.icon name="video" size="15" /> {{ __('pages.landing.steps.share.video') }}</span></div>
                </div>
            </div>
            {{-- The four voices, filled up to the chosen step: a picture of the steps above, so it says nothing of its own. --}}
            <div class="s-lr-bar" aria-hidden="true">
                @foreach (['soprano', 'alto', 'tenor', 'bass'] as $part)
                    <span class="s-v{{ $loop->iteration }}"><i></i>{{ __('pages.landing.hero.parts.'.$part) }}</span>
                @endforeach
            </div>
        </div>
    </fieldset>
    {{-- Hillel: no app replaces a real vocal coach, said on the landing (CoachLineTest), in one quiet bar with the
         way through to the whole story on its own page (site/how-it-works), but not from that page itself. --}}
    <div class="s-coachbar">
        <p class="s-coachline">{{ __('pages.landing.coach_note') }}</p>
        @unless (request()->is('how-it-works'))
            <a href="/how-it-works" class="s-arrow">{{ __('pages.landing.steps_more') }} <x-site.icon name="arrow" size="16" /></a>
        @endunless
    </div>
</section>
