{{-- A drawing of the app for a feature (the design round's direction A), made from the site's own
     parts and the hero's song (Landing::hero and Landing::pictures): never a mockup with made-up
     words, so every label is the app's own (AppWords). `name` picks it; "icon:<name>" is a feature
     still to come, drawn as its icon. Decorative: the words beside it say what it shows. --}}
@props(['name', 'hero' => null, 'pics' => null])
{{-- "shot:<id>" is a real screen of the app instead (components/site/appshot); the words beside it say what it shows. --}}
@if (str_starts_with($name, 'shot:'))
<x-site.appshot :id="substr($name, 5)" alt="" />
@else
<div class="s-pic" aria-hidden="true">
    @if (str_starts_with($name, 'icon:'))
        <div class="s-pic-icon"><x-site.icon :name="substr($name, 5)" size="40" /></div>
    @elseif ($hero === null || $pics === null)
        <div class="s-pic-icon"><x-site.icon name="spark" size="40" /></div>
    @else
        @switch($name)
            @case('modes')
                <div class="s-seg"><span>{{ \App\Support\Site\AppWords::get('practice.mode.listen') }}</span><span class="s-on">{{ \App\Support\Site\AppWords::get('practice.mode.singAlong') }}</span><span>{{ \App\Support\Site\AppWords::get('practice.mode.own') }}</span></div>
                @if ($pics['alto'])
                    <div class="s-gv-wave s-v2">{{ $pics['alto'] }}</div>
                @endif
                @break
            @case('chooser')
                {{-- How do you want to sing? (65.png): the two ways as cards, Part by part chosen. --}}
                <div class="s-pic-choose">
                    @foreach ([[\App\Support\Site\AppWords::get('sing.chooser.parts'), \App\Support\Site\AppWords::get('sing.chooser.partsTag')], [\App\Support\Site\AppWords::get('sing.chooser.whole'), \App\Support\Site\AppWords::get('sing.chooser.wholeTag')]] as [$way, $tag])
                        <span @class(['s-on' => $loop->first])>
                            <span class="s-pc-head"><i></i><b>{{ $way }}</b></span>
                            <small>{{ $tag }}</small>
                            <span class="s-pc-bars">@if ($loop->first)@foreach ([1, 0, 1, 0, 1] as $lit)<b @class(['s-v2' => $lit])></b>@endforeach @else<b class="s-v3 s-pc-long"></b><b class="s-v4 s-pc-long"></b><b class="s-v2 s-pc-long"></b>@endif</span>
                        </span>
                    @endforeach
                </div>
                @break
            @case('recording')
                {{-- The recording stage (113.png): the red label and the take, the lane, the words, how it's going. --}}
                @php($alto = \App\Support\Site\Pictures::parts($hero)['alto'] ?? null)
                <div class="s-vis-top"><span class="s-recpill">{{ \App\Support\Site\AppWords::get('sing.recordingStage.recording') }}</span><span translate="no">{{ $alto['name'] ?? '' }} · {{ \App\Support\Site\AppWords::fill('sing.recordingStage.take', ['number' => 1]) }}</span></div>
                @if ($alto)
                    <div class="s-v2">{{ \App\Support\Site\Drawings::lane($alto['notes'], 0, 12, 6.4) }}</div>
                @endif
                <p class="s-pic-words" translate="no">@foreach (array_slice($hero['words'] ?? [], 0, 7) as $word)<span @class(['s-now' => $loop->index === 3, 's-sung' => $loop->index < 3])>{{ $word['text'] }}</span>{{ $word['joins'] ? '' : ' ' }}@endforeach</p>
                <p class="s-pic-foot"><span>{{ \App\Support\Site\AppWords::fill('sing.recordingStage.onPitch', ['percent' => 82]) }}</span><i class="s-pic-stop"></i></p>
                @break
            @case('songpage')
                {{-- A song's own page (64.png): Pick your part, each range against yours, the Alto chosen. --}}
                @php($picks = \App\Support\Site\Pictures::picks($hero, ['soprano', 'alto', 'bass']))
                <div class="s-vis-top"><b>{{ \App\Support\Site\AppWords::get('template.pick') }}</b></div>
                @foreach ($picks['parts'] as $pick)
                    <div @class(['s-pic-pick', 's-v'.$pick['part']['color'], 's-on' => $pick['part']['key'] === 'alto'])>
                        <i></i><b translate="no">{{ $pick['part']['name'] }}</b>
                        <span class="s-pp-track"><span class="s-pp-you" style="left: {{ $picks['you']['left'] }}%; width: {{ $picks['you']['width'] }}%"></span><span class="s-pp-bar" style="left: {{ $pick['left'] }}%; width: {{ $pick['width'] }}%"></span></span>
                    </div>
                @endforeach
                <p class="s-pic-chips"><span class="s-on">{{ \App\Support\Site\AppWords::fill('template.learnPart', ['part' => mb_strtolower((string) (\App\Support\Site\Pictures::parts($hero)['alto']['name'] ?? ''))]) }}</span><span>{{ \App\Support\Site\AppWords::get('template.hearAll') }}</span></p>
                @break
            @case('lane')
                <div class="s-vis-top"><b translate="no">{{ $hero['title'] }}</b><span>{{ \App\Support\Site\AppWords::get('practice.mode.singAlong') }}</span></div>
                <div class="s-v1">{{ $pics['lane'] }}</div>
                @break
            @case('practice')
                <p class="s-pic-chips"><span class="s-on">{{ \App\Support\Site\AppWords::get('practice.loop') }}</span><span>{{ \App\Support\Site\AppWords::get('sing.chords.title') }}</span><span>{{ \App\Support\Site\AppWords::get('sing.settings.names.solfege') }}</span></p>
                <div class="s-v3">{{ $pics['lane'] }}</div>
                @break
            @case('views')
                <div class="s-seg"><span>{{ \App\Support\Site\AppWords::get('sing.view.words') }}</span><span>{{ \App\Support\Site\AppWords::get('sing.view.staff') }}</span><span class="s-on">{{ \App\Support\Site\AppWords::get('sing.view.notes') }}</span></div>
                @if ($melody = collect(\App\Support\Site\Pictures::parts($hero))->first())
                    <div class="s-pic-roll s-v1">{{ \App\Support\Site\Pictures::roll($hero, $melody, 44) }}</div>
                @endif
                @break
            @case('parts')
                @foreach (\App\Support\Site\Pictures::parts($hero) as $part)
                    <div class="s-trk s-v{{ $part['color'] }}"><span class="s-trk-pick"></span><span class="s-trk-name"><span translate="no">{{ $part['name'] }}</span></span><span class="s-wv">{{ \App\Support\Site\Pictures::roll($hero, $part, 22) }}</span></div>
                @endforeach
                @break
            @case('course')
                <div class="s-nodes">
                    <i class="s-v1 s-done"><x-site.icon name="check" size="16" /></i><s></s><i class="s-v2 s-now"></i><s></s><i class="s-v3"></i><s></s><i class="s-v4"></i>
                </div>
                <p class="s-stars"><x-site.icon name="star" size="22" /><x-site.icon name="star" size="22" /><x-site.icon name="star" size="22" /></p>
                @break
            @case('stack')
                @foreach ($pics['stack'] as $key => $picture)
                    <div class="s-trk s-v{{ $loop->iteration }}"><span @class(['s-trk-pick', 'gem' => $loop->last])></span><span class="s-trk-name"><span translate="no">{{ \App\Support\Site\Pictures::parts($hero)[$key]['name'] ?? $key }}</span></span><span class="s-wv" @if ($loop->last) style="clip-path: inset(0 45% 0 0)" @endif>{{ $picture }}</span></div>
                @endforeach
                <div class="s-vis-top" style="margin: 8px 0 0"><span class="s-recpill"><x-site.icon name="mic" size="13" /> {{ \App\Support\Site\AppWords::get('studio.row.recording') }}</span></div>
                @break
            @case('mix')
                @foreach ($pics['whole'] as $key => $picture)
                    <div class="s-trk s-trk-mix s-v{{ $loop->iteration }}"><span class="s-trk-pick"></span><span class="s-trk-name"><span translate="no">{{ \App\Support\Site\Pictures::parts($hero)[$key]['name'] ?? $key }}</span></span><span class="s-trk-sm"><span>{{ \App\Support\Site\AppWords::get('studio.row.soloShort') }}</span><span>{{ \App\Support\Site\AppWords::get('studio.row.muteShort') }}</span></span><span class="s-wv">{{ $picture }}</span></div>
                @endforeach
                <p class="s-pic-chips" style="margin-top: 8px"><span class="s-on">{{ \App\Support\Site\AppWords::get('polish.name') }}</span></p>
                @break
            @case('timing')
                <div class="s-vis-top"><b>{{ \App\Support\Site\AppWords::get('timing.title') }}</b><x-site.icon name="check" size="16" /></div>
                @if ($pics['alto'])
                    <div class="s-gv-wave s-v2 s-pic-late">{{ $pics['alto'] }}</div>
                    <div class="s-gv-wave s-v2">{{ $pics['alto'] }}</div>
                @endif
                @break
            @case('share')
                <div class="s-pic-card">
                    <span class="s-stackglyph">@foreach ([[1, 100], [2, 70], [3, 88], [4, 60]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                    <span><b translate="no">{{ $hero['title'] }}</b><small>{{ \App\Support\Site\AppWords::format('share.kind', ['count' => count(\App\Support\Site\Pictures::parts($hero))]) }} · {{ \App\Support\Site\AppWords::get('share.polishedTag') }}</small></span>
                </div>
                <p class="s-pic-chips"><span class="s-on"><x-site.icon name="link" size="13" /> {{ \App\Support\Site\AppWords::get('share.copy') }}</span><span>{{ \App\Support\Site\AppWords::get('share.message') }}</span><span>{{ \App\Support\Site\AppWords::get('share.email') }}</span></p>
                @break
            @case('create')
                <p class="s-pic-chips"><span class="s-on"><x-site.icon name="hum" size="13" /> {{ \App\Support\Site\AppWords::get('create.home.sing') }}</span><span>{{ \App\Support\Site\AppWords::get('create.home.write') }}</span><span>{{ \App\Support\Site\AppWords::get('create.home.chords') }}</span></p>
                @if ($melody = collect(\App\Support\Site\Pictures::parts($hero))->first())
                    <div class="s-pic-roll s-v1">{{ \App\Support\Site\Pictures::roll($hero, $melody, 44) }}</div>
                @endif
                @break
            @case('groups')
                @php($statuses = ['recorded' => \App\Support\Site\AppWords::get('groups.status.recorded'), 'started' => \App\Support\Site\AppWords::get('groups.status.started'), 'none' => \App\Support\Site\AppWords::get('groups.status.none')])
                @foreach (array_slice(\App\Support\Site\Pictures::parts($hero), 0, 3) as $part)
                    @php($status = array_keys($statuses)[$loop->index])
                    <div class="s-pic-group s-v{{ $part['color'] }}"><span class="s-disc">{{ \App\Support\Site\Pictures::initial($part) }}</span><b>{{ $part['name'] }}</b><span class="s-pic-status s-pic-{{ $status }}">@if ($status === 'recorded')<x-site.icon name="check" size="13" /> @endif{{ $statuses[$status] }}</span></div>
                @endforeach
                @break
            @case('listener')
                {{-- A share link opened (public/harmony.blade.php): what a listener sees, its player and Learn this harmony. --}}
                <p class="s-pic-eyebrow">{{ __('sharing.page.eyebrow') }}</p>
                <b class="s-pic-title" translate="no">{{ $hero['title'] }}</b>
                <div class="s-pic-player">
                    <span class="s-pic-play"><x-site.icon name="play" size="14" /></span>
                    <span class="s-pic-waves">@foreach ($pics['whole'] as $picture)<span class="s-wv s-v{{ $loop->iteration }}">{{ $picture }}</span>@endforeach</span>
                </div>
                <p class="s-pic-chips"><span class="s-on">{{ __('sharing.page.learn') }}</span><span>{{ __('sharing.page.make') }}</span></p>
                @break
            @case('video')
                {{-- Share as a video (ShareVideoRenderer): upright, the song at the top, the parts' waveforms past a now line, its words as they're sung. --}}
                <div class="s-pic-video">
                    <b translate="no">{{ $hero['title'] }}</b>
                    <span class="s-pic-vwaves">@foreach ($pics['whole'] as $picture)<span class="s-wv s-v{{ $loop->iteration }}">{{ $picture }}</span>@endforeach<i></i></span>
                    <p translate="no">{{ \App\Support\Site\Pictures::firstWords($hero, 4) }}</p>
                </div>
                @break
            @case('export')
                {{-- The Export sheet (83.png): what to export, the whole song chosen, then Export WAV. --}}
                <div class="s-vis-top"><b translate="no">{{ \App\Support\Site\AppWords::fill('export.titleOf', ['title' => $hero['title']]) }}</b></div>
                <div class="s-pic-opt s-on"><i></i><span><b>{{ \App\Support\Site\AppWords::get('export.song') }}</b><small>{{ \App\Support\Site\AppWords::format('export.songHint', ['count' => count(\App\Support\Site\Pictures::parts($hero))]) }}</small></span></div>
                <div class="s-pic-opt"><i></i><span><b>{{ \App\Support\Site\AppWords::get('export.track') }}</b><small>{{ \App\Support\Site\AppWords::get('export.trackHint') }}</small></span></div>
                <div class="s-pic-opt"><i></i><span><b>{{ \App\Support\Site\AppWords::get('export.stems') }}</b><small>{{ \App\Support\Site\AppWords::get('export.stemsChoice') }}</small></span><em>{{ \App\Support\Site\AppWords::get('home.plus') }}</em></div>
                <p class="s-pic-chips"><span class="s-on">{{ \App\Support\Site\AppWords::fill('export.exportAs', ['format' => 'WAV']) }}</span></p>
                @break
            @case('trim')
                {{-- Trim (82.png): the take with its cut ends and a grip at each, and how much is kept. --}}
                @php($alto = \App\Support\Site\Pictures::parts($hero)['alto'] ?? null)
                <div class="s-vis-top"><b>{{ \App\Support\Site\AppWords::fill('trim.title', ['part' => $alto['name'] ?? '']) }}</b></div>
                <div class="s-pic-trim s-v2"><span class="s-wv">{{ $pics['alto'] }}</span><i class="s-cut-l"></i><i class="s-cut-r"></i></div>
                <div class="s-vis-top"><span>{{ \App\Support\Site\AppWords::fill('trim.keeping', \App\Support\Site\Pictures::kept($hero)) }}</span></div>
                @break
            @case('stars')
                {{-- The course's progress: stars, your level and its XP, and the days this week. --}}
                <p class="s-stars"><x-site.icon name="star" size="18" /><x-site.icon name="star" size="18" /><x-site.icon name="star" size="18" /></p>
                <div class="s-vis-top"><b>{{ \App\Support\Site\AppWords::fill('voice.levelXp', ['level' => 2, 'xp' => 150]) }}</b></div>
                <div class="s-pic-bar"><i style="width: 60%"></i></div>
                <div class="s-vis-top" style="margin-top: 12px"><span>{{ \App\Support\Site\AppWords::get('home.week.days') }}</span><span>{{ \App\Support\Site\AppWords::fill('course.goal.days', ['count' => 3]) }}</span></div>
                <p class="s-pic-week">@foreach ([1, 1, 0, 0, 0, 0, 0] as $sang)<i @class(['gem' => $sang])></i>@endforeach</p>
                @break
            @case('workout')
                {{-- Today's workout (85.png): the warm-up's headline, its rounds, and the starting note to shift. --}}
                <div class="s-vis-top"><span>{{ \App\Support\Site\AppWords::get('workout.title') }}</span><span>{{ \App\Support\Site\AppWords::get('workout.step.warmUp') }}</span></div>
                <b class="s-pic-title">{{ \App\Support\Site\AppWords::format('workout.warmUpTitle', ['climb' => 5]) }}</b>
                <p class="s-pic-rounds"><i class="gem">1</i><i class="s-now">2</i><i>3</i></p>
                <p class="s-pic-chips"><span>{{ \App\Support\Site\AppWords::get('keyControl.lower') }}</span><span>{{ \App\Support\Site\AppWords::get('keyControl.higher') }}</span></p>
                @break
            @case('voices')
                {{-- A part's Guide voice (107.png): a real singer's voice chosen, another, or the practice voice. --}}
                <div class="s-vis-top"><b>{{ \App\Support\Site\AppWords::get('part.guideVoice') }}</b></div>
                <div class="s-pic-opt s-on"><i></i><span><b>{{ \App\Support\Site\AppWords::get('part.sungByAnonymous') }}</b><small>{{ \App\Support\Site\AppWords::get('part.defaultVoice') }}</small></span></div>
                <div class="s-pic-opt"><i></i><span><b>{{ \App\Support\Site\AppWords::get('part.sungByAnonymous') }}</b><small>{{ \App\Support\Site\AppWords::get('part.otherVoice') }}</small></span></div>
                <div class="s-pic-opt"><i></i><span><b>{{ \App\Support\Site\AppWords::get('part.practiceVoice') }}</b><small>{{ \App\Support\Site\AppWords::get('part.practiceVoiceHint') }}</small></span></div>
                @break
            @case('singline')
                {{-- The creator's Sing step: a line of words to sing, its notes as they're found, Sing line 1. --}}
                <div class="s-vis-top"><b>{{ \App\Support\Site\AppWords::fill('create.sing.lineOf', ['number' => 1, 'total' => 4]) }}</b></div>
                <p class="s-pic-line" translate="no">{{ \App\Support\Site\Pictures::firstWords($hero, 6) }}</p>
                @if ($melody = collect(\App\Support\Site\Pictures::parts($hero))->first())
                    <div class="s-pic-roll s-v1">{{ \App\Support\Site\Pictures::roll($hero, $melody, 44) }}</div>
                @endif
                <p class="s-pic-chips"><span class="s-on"><x-site.icon name="mic" size="13" /> {{ \App\Support\Site\AppWords::fill('create.sing.singLine', ['number' => 1]) }}</span></p>
                @break
            @case('assign')
                {{-- A choir group's song (90.png): its parts, nobody given one yet, and Assign parts. --}}
                <div class="s-vis-top"><b translate="no">{{ $hero['title'] }}</b></div>
                @foreach (array_slice(\App\Support\Site\Pictures::parts($hero), 0, 3) as $part)
                    <div class="s-pic-group s-v{{ $part['color'] }}"><span class="s-disc">{{ \App\Support\Site\Pictures::initial($part) }}</span><b>{{ $part['name'] }}</b><span class="s-pic-status">{{ \App\Support\Site\AppWords::get('groups.nobody') }}</span></div>
                @endforeach
                <p class="s-pic-chips"><span class="s-on">{{ \App\Support\Site\AppWords::get('groups.assign') }}</span></p>
                @break
            @case('public')
                {{-- Share, once the link is made: the harmony, featuring ticked, and what that means for its parts as guides. --}}
                <div class="s-vis-top"><b translate="no">{{ $hero['title'] }}</b><span>{{ \App\Support\Site\AppWords::get('share.title') }}</span></div>
                <p class="s-pic-tick"><i><x-site.icon name="check" size="12" /></i><span>{{ \App\Support\Site\AppWords::get('share.feature') }}</span></p>
                <p class="s-pic-note"><x-site.icon name="info" size="15" /><span>{{ \App\Support\Site\AppWords::get('share.guides') }}</span></p>
                @break
            @default
                <div class="s-pic-icon"><x-site.icon name="spark" size="40" /></div>
        @endswitch
    @endif
</div>
@endif
