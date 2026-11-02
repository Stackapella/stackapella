{{-- Ear training's Name that leap (js/public/ear-training.js): five leaps of three, four or five to name
     by ear, the songs that start with each as hints, no mic. Used by the Ear training page and, in the
     shared demo slot, by Music teachers and schools; its words are always Ear training's. --}}
        @php($demo = (array) \Illuminate\Support\Facades\Lang::get('site.courses.pages.ear-training.demo'))
        <div class="cp-dk-demo" data-ear-demo
            data-round="{{ $demo['round'] }}" data-listen="{{ $demo['listen'] }}" data-yes="{{ $demo['yes'] }}" data-no="{{ $demo['no'] }}" data-score="{{ $demo['score'] }}"
            data-next="{{ $demo['next'] }}" data-again="{{ $demo['again'] }}" data-three="{{ $demo['leap']['three'] }}" data-four="{{ $demo['leap']['four'] }}" data-five="{{ $demo['leap']['five'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['kick'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-dk-demo-in">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <fieldset class="cp-dk-pick">
                        <legend>{{ $demo['songs_h'] }}</legend>
                        @foreach ((array) $demo['songs'] as $leap => $song)
                            <button type="button" class="cp-dk-hear" data-ear-song="{{ $leap }}" data-play="{{ $song }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $song }}</span></button>
                        @endforeach
                    </fieldset>
                </div>
                <div class="cp-ear-quiz">
                    <div class="cp-dk-roll cp-ear-roll">
                        <p class="cp-ear-round" data-ear-round></p>
                        <svg viewBox="0 0 200 230" aria-hidden="true">
                            <line x1="100" y1="205" x2="100" y2="205" class="cp-dk-trace" data-ear-jump data-base="205" data-gap="26" hidden />
                            @foreach (range(1, 8) as $rung)
                                <path d="M40 {{ 205 - ($rung - 1) * 26 }} H160" class="cp-dk-staff" />
                                <text x="22" y="{{ 210 - ($rung - 1) * 26 }}" class="cp-ear-n">{{ $rung }}</text>
                                <circle cx="100" cy="{{ 205 - ($rung - 1) * 26 }}" r="9" class="cp-ear-rung" data-ear-rung="{{ $rung }}" />
                            @endforeach
                        </svg>
                    </div>
                    <p class="cp-dk-btns"><button type="button" class="cp-dk-play cp-dk-play-b" data-ear-play data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button></p>
                    <fieldset class="cp-dk-pick">
                        <legend>{{ $demo['picks_h'] }}</legend>
                        @foreach ((array) $demo['picks'] as $leap => $name)
                            <button type="button" class="cp-dk-chip cp-ear-pick" data-ear-pick="{{ $leap }}">{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-say" role="status" data-ear-say></p>
                    <p class="cp-dk-btns"><button type="button" class="cp-dk-chip cp-ear-next" data-ear-next hidden>{{ $demo['next'] }}</button></p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
