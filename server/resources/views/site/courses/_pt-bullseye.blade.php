{{-- Perfect your pitch's Bullseye (js/public/perfect-your-pitch.js, inside a [data-pt] root): the guide sings
     five notes and the visitor echoes each, the mic only on its own tap, or Pull it in by ear without one.
     Used by the course page and, in the shared demo slot, by the How to sing in tune guide; its words are
     always the course's. --}}
@php($love = (array) \Illuminate\Support\Facades\Lang::get('site.courses.pages.perfect-your-pitch.love'))
@php($demo = (array) $love['demo'])
@php($board = [[150, 'w'], [128, 'k'], [104, 'b'], [66, 'r'], [30, 'g']])
@php($headId = $headId ?? 'demo-h')
        <div class="cp-pt2-demo" data-pt-demo
            data-asking="{{ $demo['asking'] }}" data-listen="{{ $demo['listen'] }}" data-turn="{{ $demo['turn'] }}"
            data-sharp="{{ $demo['sharp'] }}" data-flat="{{ $demo['flat'] }}" data-dead-on="{{ $demo['dead_on'] }}"
            data-unheard="{{ $demo['unheard'] }}" data-summary="{{ $demo['summary'] }}" data-none="{{ $demo['none'] }}"
            data-why-denied="{{ $demo['why_denied'] }}" data-why-no-mic="{{ $demo['why_no_mic'] }}" data-why-unsupported="{{ $demo['why_unsupported'] }}">
            <p class="cp-pt2-demo-bar"><span class="cp-pt2-live"><span class="cp-pt2-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-pt2-demo-in">
                <div class="cp-pt2-board" aria-hidden="true">
                    <svg viewBox="0 0 340 380" data-pt-board>
                        @foreach ($board as [$r, $c])
                            <circle cx="170" cy="190" r="{{ $r }}" class="cp-pt2-ring cp-pt2-{{ $c }}" />
                        @endforeach
                        <path d="M170 34 V346 M14 190 H326" class="cp-pt2-cross" />
                        @foreach ([[30, 10], [66, 25], [104, 50]] as [$r, $cents])
                            <text x="{{ 170 + $r - 5 }}" y="185" class="cp-pt2-reach-n">{{ $cents }}</text>
                        @endforeach
                        <text x="170" y="22" class="cp-pt2-side">{{ $demo['up'] }}</text>
                        <text x="170" y="374" class="cp-pt2-side">{{ $demo['down'] }}</text>
                        <g data-pt-darts></g>
                    </svg>
                </div>
                <div class="cp-pt2-demo-copy" @if ($headId === 'demo-h') id="bullseye" @endif>
                    <h2 id="{{ $headId }}" class="cp-pt2-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-pt2-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-pt2-btns">
                        <button type="button" class="cp-pt2-play cp-pt2-play-mic" data-pt-mic data-play="{{ $demo['mic'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-pt2-play-icon" aria-hidden="true"><x-site.icon name="mic" size="18" /></span><span data-label>{{ $demo['mic'] }}</span></button>
                        <button type="button" class="cp-pt2-ear" data-pt-ear>{{ $demo['by_ear'] }}</button>
                    </p>
                    <label class="cp-pt2-mic-pick" hidden><span>{{ $demo['mic_pick'] }}</span><select data-pt-mic-pick></select></label>
                    <p class="cp-pt2-say" role="status" data-pt-say>{{ $demo['ready'] }}</p>
                    <p class="cp-pt2-private"><x-site.icon name="device" size="16" /><span>{{ $demo['private'] }}</span></p>
                    <div class="cp-pt2-pull" data-pt-pull hidden data-listen="{{ $love['pull']['listen'] }}" data-won="{{ $love['pull']['won'] }}">
                        <p class="cp-pt2-why" data-pt-why></p>
                        <h3 tabindex="-1">{{ $love['pull']['title'] }}</h3>
                        <p class="cp-pt2-demo-lead">{{ $love['pull']['lead'] }}</p>
                        <label class="cp-pt2-slider"><span>{{ $love['pull']['slider'] }}</span><input type="range" min="-100" max="100" step="1" value="40" data-pt-slider></label>
                        <p class="cp-pt2-btns"><button type="button" class="cp-pt2-play" data-pt-pull-play data-play="{{ $love['pull']['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-pt2-play-icon" aria-hidden="true"></span><span data-label>{{ $love['pull']['play'] }}</span></button></p>
                        <p class="cp-pt2-say" role="status" data-pt-pull-say></p>
                        <div class="cp-pt2-win" data-pt-win hidden>
                            <p class="cp-pt2-win-h"><x-site.skin :game="$love['pull']['won_game']" :serious="$love['pull']['won_serious']" /></p>
                            <p class="cp-pt2-win-say" data-pt-win-say></p>
                            <p class="cp-pt2-btns"><button type="button" class="cp-pt2-play" data-pt-again><span class="cp-pt2-play-icon" aria-hidden="true"></span><span>{{ $love['pull']['again'] }}</span></button></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
