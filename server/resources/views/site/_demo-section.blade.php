{{-- An audience or feature page's live demo (docs/demo-pages-plan.md): the shared slot near the top, one tap,
     no account; `$page['demo']`'s kind picks the demo. Included by site/for and site/page. --}}
        @if (! empty($page['demo']))
            @php($demo = $page['demo'])
            @php($line = in_array($demo['kind'], ['mixer', 'learn'], true) ? \App\Support\Pages\Landing::hero() : null)
            @php($line = in_array($demo['kind'], ['studio', 'stack', 'voices'], true) ? \App\Support\Pages\Landing::lineOf($demo['song']) : $line)
            @if (! in_array($demo['kind'], ['mixer', 'studio', 'stack', 'learn', 'voices'], true) || $line !== null)
                <section class="s-wrap s-sec s-demosec" aria-labelledby="demo-h">
                    <div class="s-demohead">
                        <h2 id="demo-h" class="s-title">{{ $demo['title'] }}</h2>
                        <p class="s-lede">{{ $demo['lead'] }}</p>
                    </div>
                    @if ($demo['kind'] === 'mixer')
                        <x-site.demo src="/js/public/demos/mixer.js" fit="content" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <x-site.mixer :song="$line" id="demo-mixer" />
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'studio')
                        <x-site.demo src="/js/public/demos/mixer.js" fit="content" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <x-site.mixer :song="$line" id="demo-studio" :studio="true" />
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'stack')
                        {{-- The stack (js/public/demos/stack.js; A cappella, Friends and family): the mixer with the first part of `order` alone, and Add bringing in the next over it. Each page has its own words. --}}
                        @php($line['you'] = $demo['order'][0])
                        {{-- @json splits its argument at commas, so the words are one variable. --}}
                        @php($stackWords = ['add' => $demo['add'], 'steps' => $demo['steps'], 'again' => $demo['again']])
                        <x-site.demo src="/js/public/demos/stack.js" fit="content" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-stack" data-stack data-order="{{ implode(',', $demo['order']) }}" @if ($demo['forward'] ?? false) data-forward @endif>
                                <script type="application/json" data-stack-words>@json($stackWords)</script>
                                <x-site.mixer :song="$line" id="demo-stack" :on="[$demo['order'][0]]">
                                    <button type="button" class="s-stack-add" data-stack-add><x-site.icon name="plus" size="18" /><span data-label>{{ $demo['add'][$demo['order'][1]] }}</span></button>
                                </x-site.mixer>
                                <p class="s-stack-say" role="status" data-stack-say>{{ $demo['steps'][$demo['order'][0]] }}</p>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'learn')
                        @php($ld = (array) \Illuminate\Support\Facades\Lang::get('pages.site.learn_demo'))
                        @php($line['you'] = $demo['you'] ?? $line['you'])
                        {{-- Learn a part's still frame: the line's four parts on one lane, the melody bright, as js/public/demos/learn-part.js draws it. --}}
                        @php($pitches = [])
                        @foreach ($line['parts'] as $part)
                            @php($pitches = [...$pitches, ...array_column($part['notes'], 'pitch')])
                        @endforeach
                        @php($lo = min($pitches) - 1)
                        @php($span = max(1, max($pitches) + 1 - $lo))
                        <x-site.demo src="/js/public/demos/learn-part.js" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-lp" data-learn data-guide="{{ $ld['guide'] }}" data-you="{{ $ld['you'] }}">
                                <script type="application/json" data-song>@json($line)</script>
                                <svg viewBox="0 0 600 220" preserveAspectRatio="none" class="s-lp-lane" data-learn-lane aria-hidden="true">
                                    @foreach ($line['parts'] as $part)
                                        @foreach ($part['notes'] as $note)
                                            <rect x="{{ round(12 + $note['beat'] / $line['beats'] * 576, 1) }}" y="{{ round(206 - ($note['pitch'] - $lo) / $span * 200, 1) }}" width="{{ round(max(4, $note['length'] / $line['beats'] * 576 - 2), 1) }}" height="8" rx="4" @class(['s-lp-note', 's-v'.$part['color'], 's-lp-you' => $part['key'] === $line['you']]) />
                                        @endforeach
                                    @endforeach
                                </svg>
                                <div class="s-lp-controls">
                                    <div class="s-lp-group" role="group" aria-label="{{ $ld['part'] }}"><span class="s-lp-h" aria-hidden="true">{{ $ld['part'] }}</span>@foreach ($line['parts'] as $part)<button type="button" class="s-lp-chip s-v{{ $part['color'] }}" data-learn-part="{{ $part['key'] }}" aria-pressed="{{ $part['key'] === $line['you'] ? 'true' : 'false' }}">{{ $part['name'] }}</button>@endforeach</div>
                                    <div class="s-lp-group" role="group" aria-label="{{ $ld['speed'] }}"><span class="s-lp-h" aria-hidden="true">{{ $ld['speed'] }}</span>@foreach ((array) $ld['speeds'] as [$value, $name])<button type="button" class="s-lp-chip" data-learn-speed="{{ $value }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $name }}</button>@endforeach</div>
                                    <div class="s-lp-group" role="group"><button type="button" class="s-lp-chip" data-learn-mode="guide" aria-pressed="true">{{ $ld['mode_guide'] }}</button><button type="button" class="s-lp-chip" data-learn-mode="you" aria-pressed="false">{{ $ld['mode_you'] }}</button></div>
                                    <button type="button" class="s-cb-play" data-learn-play data-play="{{ $ld['play'] }}" data-stop="{{ $ld['stop'] }}" aria-pressed="false"><span class="s-cb-play-icon" aria-hidden="true"></span><span data-label>{{ $ld['play'] }}</span></button>
                                </div>
                                <p class="s-cb-say" role="status" data-learn-say>{{ $ld['ready'] }}</p>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'voices')
                        {{-- Real voices' A/B (js/public/demos/real-voices.js on ab-voice.js): one part of the line, lit on the
                             learn demo's lane, sung by the practice voice or a real singer, switched while it plays. --}}
                        @php($vd = (array) \Illuminate\Support\Facades\Lang::get('pages.site.voices_demo'))
                        @php($line['you'] = $demo['part'])
                        @php($abPart = collect($line['parts'])->firstWhere('key', $demo['part'])['name'] ?? '')
                        @php($pitches = collect($line['parts'])->flatMap(fn (array $p): array => array_column($p['notes'], 'pitch'))->all())
                        @php($lo = min($pitches) - 1)
                        @php($span = max(1, max($pitches) + 1 - $lo))
                        <x-site.demo src="/js/public/demos/real-voices.js" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-lp" data-ab data-part="{{ $demo['part'] }}" data-say-synth="{{ str_replace(':part', $abPart, $vd['synth_say']) }}" data-say-real="{{ str_replace(':part', $abPart, $vd['real_say']) }}" data-missing="{{ str_replace(':part', $abPart, $vd['missing']) }}" data-failed="{{ str_replace(':part', $abPart, $vd['failed']) }}" data-credit="{{ $vd['credit'] }}">
                                <script type="application/json" data-ab-song>@json($line)</script>
                                <svg viewBox="0 0 600 220" preserveAspectRatio="none" class="s-lp-lane" aria-hidden="true">
                                    @foreach ($line['parts'] as $part)
                                        @foreach ($part['notes'] as $note)
                                            <rect x="{{ round(12 + $note['beat'] / $line['beats'] * 576, 1) }}" y="{{ round(206 - ($note['pitch'] - $lo) / $span * 200, 1) }}" width="{{ round(max(4, $note['length'] / $line['beats'] * 576 - 2), 1) }}" height="8" rx="4" @class(['s-lp-note', 's-v'.$part['color'], 's-lp-you' => $part['key'] === $line['you']]) />
                                        @endforeach
                                    @endforeach
                                </svg>
                                <div class="s-lp-controls">
                                    <div class="s-lp-group" role="group" aria-label="{{ str_replace(':part', $abPart, $vd['who']) }}"><span class="s-lp-h" aria-hidden="true">{{ str_replace(':part', $abPart, $vd['who']) }}</span><button type="button" class="s-lp-chip" data-ab-use="synth" aria-pressed="true" disabled>{{ $vd['synth'] }}</button><button type="button" class="s-lp-chip" data-ab-use="real" aria-pressed="false" disabled>{{ $vd['real'] }}</button></div>
                                    <button type="button" class="s-cb-play" data-ab-play data-play="{{ $vd['play'] }}" data-stop="{{ $vd['stop'] }}" aria-pressed="false" disabled><span class="s-cb-play-icon" aria-hidden="true"></span><span data-label>{{ $vd['play'] }}</span></button>
                                </div>
                                <p class="s-cb-say" role="status" data-ab-say>{{ $vd['ready'] }}</p>
                                <p class="s-cb-say" data-ab-credit hidden></p>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'note')
                        {{-- Find your comfortable note: the app's own card (js/public/voice-capture.js), mounted on the start; the still
                             frame is only its outline and first words, so there's one card, never a second version. --}}
                        @php($nd = (array) \Illuminate\Support\Facades\Lang::get('pages.site.note_demo'))
                        <x-site.demo src="/js/public/demos/comfortable-note.js" fit="content" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-capture" data-capture data-why-denied="{{ $nd['why_denied'] }}" data-why-no-mic="{{ $nd['why_no_mic'] }}" data-why-unsupported="{{ $nd['why_unsupported'] }}">
                                <script type="application/json" data-capture-words>@json($nd['card'])</script>
                                <div class="s-capture-card" data-capture-card>
                                    <div class="s-capture-still" aria-hidden="true">
                                        <svg viewBox="0 0 152 152" width="152" height="152"><circle cx="76" cy="76" r="69" fill="none" stroke="var(--color-raised)" stroke-width="8" /></svg>
                                        <p class="s-capture-still-h">{{ $nd['card']['listening'] }}</p>
                                        <p class="s-capture-still-sub">{{ $nd['card']['listeningSub'] }}</p>
                                    </div>
                                </div>
                                <p class="s-cb-say s-capture-say" role="status" data-capture-say></p>
                                <p class="s-note-private"><x-site.icon name="device" size="16" /><span>{{ $nd['private'] }}</span></p>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'chords')
                        @php($cb = (array) \Illuminate\Support\Facades\Lang::get('pages.site.chord_builder'))
                        {{-- The chord builder's still frame: the tune's own notes (js/public/chord-builder.js's TUNE) and the arranger's chords. --}}
                        @php($tune = [[0, 1, 62], [1, 2, 67], [3, 0.5, 71], [3.5, 0.5, 67], [4, 2, 71], [6, 1, 69], [7, 2, 67], [9, 1, 64], [10, 2, 62], [12, 1, 62], [13, 2, 67], [15, 0.5, 71], [15.5, 0.5, 67], [16, 2, 71], [18, 1, 69], [19, 5, 74]])
                        <x-site.demo src="/js/public/demos/chord-builder.js" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-cb" data-cb
                                data-chords-label="{{ $cb['chords_label'] }}" data-pickup="{{ $cb['pickup'] }}" data-bar="{{ $cb['bar'] }}" data-chip-label="{{ $cb['chip_label'] }}"
                                data-play="{{ $cb['play'] }}" data-stop="{{ $cb['stop'] }}" data-singers="{{ $cb['singers'] }}" data-singers-now="{{ $cb['singers_now'] }}"
                                data-reset="{{ $cb['reset'] }}" data-ready="{{ $cb['ready'] }}" data-swapped="{{ $cb['swapped'] }}"
                                data-part-soprano="{{ $cb['parts']['soprano'] }}" data-part-alto="{{ $cb['parts']['alto'] }}" data-part-tenor="{{ $cb['parts']['tenor'] }}" data-part-bass="{{ $cb['parts']['bass'] }}">
                                <div class="s-cb-roll">
                                    <svg viewBox="0 0 600 210" preserveAspectRatio="none" class="s-cb-lane" aria-hidden="true">
                                        @foreach ($tune as [$at, $long, $pitch])
                                            <rect x="{{ round(12 + $at / 24 * 576, 1) }}" y="{{ round(200 - ($pitch - 60) * 12.5 - 4, 1) }}" width="{{ round(max(4, $long / 24 * 576 - 2), 1) }}" height="8" rx="4" class="s-cb-note s-cb-soprano" />
                                        @endforeach
                                    </svg>
                                    <p class="s-cb-words" aria-hidden="true">@foreach ((array) $cb['lyric'] as $word)<span>{{ $word }}</span>@endforeach</p>
                                </div>
                                <div class="s-cb-chips" aria-hidden="true">@foreach ((array) $cb['still'] as $chord)<span class="s-cb-chip"><b>{{ $chord }}</b></span>@endforeach</div>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'choir')
                        {{-- Choir groups' still frame: the director's view on Monday, as js/public/demos/choir-board.js draws it. --}}
                        @php($cd = (array) \Illuminate\Support\Facades\Lang::get('pages.site.choir_demo'))
                        @php($hero = \App\Support\Pages\Landing::hero())
                        @php($parts = collect($hero['parts'] ?? [])->map(fn (array $p): array => ['key' => $p['key'], 'name' => $p['name'], 'color' => $p['color']])->all())
                        @php($members = collect($cd['members'])->map(fn (array $m): array => ['name' => $m[0], 'part' => $m[1], 'status' => $m[2], 'pace' => $m[3]])->all())
                        @php($assigned = collect($members)->whereNotNull('part')->values())
                        @php($covered = $assigned->pluck('part')->unique()->count())
                        @php($open = collect($parts)->whereNotIn('key', $assigned->pluck('part'))->pluck('name'))
                        {{-- The app's summary (js/views/groups.js): covered, recorded, and the parts nobody has while some do. --}}
                        @php($summary = __('pages.site.choir_demo.covered', ['count' => $covered, 'total' => count($parts)]).' · '.__('pages.site.choir_demo.recorded', ['count' => $assigned->where('status', 'recorded')->count()]))
                        @php($summary = $open->isNotEmpty() && $open->count() < count($parts) ? $summary.' · '.__('pages.site.choir_demo.no_one', ['parts' => $open->join(', ', $cd['or'])]) : $summary)
                        @php($words = ['song' => $hero['title'] ?? '', 'assign' => $cd['assign'], 'done' => $cd['done'], 'partFor' => $cd['part_for'], 'noPart' => $cd['no_part'], 'whoSings' => $cd['who_sings'], 'nobody' => $cd['nobody'], 'nobodyYet' => $cd['nobody_yet'], 'covered' => $cd['covered'], 'recorded' => $cd['recorded'], 'noOne' => $cd['no_one'], 'status' => $cd['status'], 'next' => $cd['next'], 'again' => $cd['again'], 'days' => $cd['days'], 'dayNow' => $cd['day_now'], 'rehearsal' => $cd['rehearsal'], 'gap' => $cd['gap'], 'assigned' => $cd['assigned'], 'unassigned' => $cd['unassigned']])
                        @php($board = ['song' => $hero['title'] ?? '', 'parts' => $parts, 'members' => $members, 'words' => $words])
                        <x-site.demo src="/js/public/demos/choir-board.js" fit="content" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-gb" data-choir>
                                <script type="application/json" data-choir-data>@json($board)</script>
                                <div class="s-gb-card" data-choir-board>
                                    <div class="s-gb-head">
                                        <div class="s-gb-t"><p class="s-gb-song">{{ $hero['title'] ?? '' }}</p><p class="s-gb-sum">{{ $summary }}</p></div>
                                        <span class="s-gb-btn" aria-hidden="true">{{ $cd['assign'] }}</span>
                                    </div>
                                    <ul class="s-gb-strip" aria-label="{{ $cd['who_sings'] }}">
                                        @foreach ($parts as $part)
                                            @php($who = $assigned->where('part', $part['key']))
                                            <li class="s-gb-part s-v{{ $part['color'] }}">
                                                <span class="s-gb-pname"><span class="s-gb-dot" aria-hidden="true"></span><span>{{ $part['name'] }}</span></span>
                                                @forelse ($who as $m)
                                                    <span class="s-gb-who"><b>{{ $m['name'] }}</b><small class="s-gb-{{ $m['status'] }}">{{ $cd['status'][$m['status']] }}</small></span>
                                                @empty
                                                    <span class="s-gb-nobody">{{ $cd['nobody'] }}</span>
                                                @endforelse
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="s-gb-foot">
                                    <button type="button" class="s-stack-add" data-choir-next><span data-label>{{ $cd['next'] }}</span></button>
                                    <p class="s-stack-say" role="status" data-choir-say></p>
                                </div>
                                <p class="s-gb-private">{{ $cd['private'] }}</p>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'warmup')
                        {{-- A warm-up card's still frame: the first pattern's notes, as js/public/demos/warmup-card.js draws them. --}}
                        @php($wd = (array) \Illuminate\Support\Facades\Lang::get('pages.site.warmup_demo'))
                        @php($wuPatterns = collect($wd['patterns'])->map(fn (array $p): array => ['key' => $p[0], 'name' => $p[1], 'steps' => $p[2]])->all())
                        @php($wuData = ['patterns' => $wuPatterns, 'speeds' => collect($wd['speeds'])->map(fn (array $s): array => ['tempo' => $s[0], 'name' => $s[1]])->all(), 'climbs' => collect($wd['climbs'])->map(fn (array $c): array => ['climb' => $c[0], 'name' => $c[1]])->all(), 'words' => ['ready' => $wd['ready'], 'round' => $wd['round']]])
                        @php($first = $wuPatterns[0]['steps'])
                        @php($wuBeats = count($first) - 1 + 3)
                        <x-site.demo src="/js/public/demos/warmup-card.js" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-lp s-wu" data-wu>
                                <script type="application/json" data-wu-data>@json($wuData)</script>
                                <svg viewBox="0 0 600 180" preserveAspectRatio="none" class="s-wu-lane" data-wu-lane aria-hidden="true">
                                    @foreach ($first as $i => $step)
                                        @php($long = $loop->last ? 3 : 1)
                                        <rect x="{{ round(12 + $i / $wuBeats * 576, 1) }}" y="{{ round(168 - ($step + 1) / 14 * 156 - 5, 1) }}" width="{{ round(max(10, $long / $wuBeats * 576 - 4), 1) }}" height="10" rx="5" class="s-wu-note" />
                                    @endforeach
                                </svg>
                                <div class="s-lp-controls">
                                    <div class="s-lp-group" role="group" aria-label="{{ $wd['pattern'] }}"><span class="s-lp-h" aria-hidden="true">{{ $wd['pattern'] }}</span>@foreach ($wuPatterns as $p)<button type="button" class="s-lp-chip" data-wu-pattern="{{ $p['key'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $p['name'] }}</button>@endforeach</div>
                                    <div class="s-lp-group" role="group" aria-label="{{ $wd['speed'] }}"><span class="s-lp-h" aria-hidden="true">{{ $wd['speed'] }}</span>@foreach ($wd['speeds'] as [$tempo, $name])<button type="button" class="s-lp-chip" data-wu-speed="{{ $tempo }}" aria-pressed="{{ $loop->index === 1 ? 'true' : 'false' }}">{{ $name }}</button>@endforeach</div>
                                    <div class="s-lp-group" role="group" aria-label="{{ $wd['climb'] }}"><span class="s-lp-h" aria-hidden="true">{{ $wd['climb'] }}</span>@foreach ($wd['climbs'] as [$climb, $name])<button type="button" class="s-lp-chip" data-wu-climb="{{ $climb }}" aria-pressed="{{ $loop->index === 1 ? 'true' : 'false' }}">{{ $name }}</button>@endforeach</div>
                                    <button type="button" class="s-cb-play" data-wu-play data-play="{{ $wd['play'] }}" data-stop="{{ $wd['stop'] }}" aria-pressed="false"><span class="s-cb-play-icon" aria-hidden="true"></span><span data-label>{{ $wd['play'] }}</span></button>
                                </div>
                                <p class="s-cb-say" role="status" data-wu-say>{{ $wd['ready'] }}</p>
                                <p class="s-wu-open"><a href="{{ \App\Support\Http\Hosts::app('/warmup') }}" data-wu-open>{{ $wd['open'] }}</a> <span>{{ $wd['open_note'] }}</span></p>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'vibrato')
                        {{-- Vibrato's Straight, wave, wobble: the course page's words and its script's hooks (js/public/vibrato.js), on the dark stage. --}}
                        @php($vd = (array) \Illuminate\Support\Facades\Lang::get('site.courses.pages.vibrato.demo'))
                        <x-site.demo src="/js/public/demos/vibrato.js" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-lp s-vi" data-vi-demo
                                data-straight="{{ $vd['say_straight'] }}" data-wave="{{ $vd['say_wave'] }}" data-wobble="{{ $vd['say_wobble'] }}" data-own="{{ $vd['say_own'] }}"
                                data-after="{{ $vd['after'] }}" data-rate-out="{{ $vd['rate_out'] }}" data-width-out="{{ $vd['width_out'] }}">
                                <svg viewBox="0 0 600 220" preserveAspectRatio="none" class="s-vi-wave" aria-hidden="true">
                                    <path d="M20 20 H580 M20 200 H580" class="s-vi-staff" />
                                    <path d="M20 110 H580" class="s-vi-staff s-vi-centre" />
                                    <path d="M20 110 H580" class="s-vi-trace" data-vi-trace />
                                </svg>
                                <div class="s-lp-controls">
                                    <div class="s-lp-group" role="group">@foreach (['straight', 'wave', 'wobble'] as $key)<button type="button" class="s-lp-chip" data-vi-play="{{ $key }}" data-play="{{ $vd[$key] }}" data-stop="{{ $vd[$key] }}" aria-pressed="false"><span data-label>{{ $vd[$key] }}</span></button>@endforeach</div>
                                </div>
                                <div class="s-vi-sliders">
                                    <label class="s-vi-slider"><span>{{ $vd['rate'] }} <output data-vi-rate-out></output></span><input type="range" min="0" max="8" step="0.5" value="6" data-vi-rate></label>
                                    <label class="s-vi-slider"><span>{{ $vd['width'] }} <output data-vi-width-out></output></span><input type="range" min="0" max="150" step="5" value="50" data-vi-width></label>
                                </div>
                                <p class="s-lp-controls"><button type="button" class="s-cb-play" data-vi-play="own" data-play="{{ $vd['own_play'] }}" data-stop="{{ $vd['stop'] }}" aria-pressed="false"><span class="s-cb-play-icon" aria-hidden="true"></span><span data-label>{{ $vd['own_play'] }}</span></button></p>
                                <p class="s-cb-say" role="status" data-vi-say>{{ $vd['ready'] }}</p>
                            </div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'bullseye')
                        <x-site.demo src="/js/public/demos/bullseye.js" fit="content" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="cp-pt2 s-ptslot" data-pt>@include('site.courses._pt-bullseye', ['headId' => 'pt-demo-h'])</div>
                        </x-site.demo>
                    @elseif ($demo['kind'] === 'leap')
                        <x-site.demo src="/js/public/demos/ear-leap.js" fit="content" :caption="$demo['caption']" :start="$demo['start']" :label="$demo['title']">
                            <div class="s-demo-leap">@include('site.courses._ear-leap')</div>
                        </x-site.demo>
                    @endif
                </section>
            @endif
        @endif

