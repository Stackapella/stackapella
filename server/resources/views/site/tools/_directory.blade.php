{{-- More free tools (docs/tools/below-the-fold.md): the featured next tool (this tool's `related`, when it's live),
     then every other live tool in its group, in the same five groups on every page, and "See all :count tools". --}}
@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))
@if ($directory['featured'] !== null || $directory['groups'] !== [])
    <section class="s-wrap s-sec s-tdir" aria-labelledby="tools-h">
        <div class="s-tdir-head">
            <h2 id="tools-h">{{ $ix['others'] }}</h2>
            <p>{{ $ix['others_sub'] }}</p>
            <a href="/tools" class="s-lk">{{ str_replace(':count', (string) $directory['count'], $ix['see_all']) }} <x-site.icon name="arrow" size="16" /></a>
        </div>
        @if ($directory['featured'] !== null)
            @php($featured = $directory['featured'])
            <a href="{{ $featured['url'] }}" class="s-tfeat s-v{{ $featured['colour'] }}">
                <span class="s-who-ic" aria-hidden="true"><x-site.icon :name="$featured['icon']" size="22" /></span>
                <span><b>{{ $featured['line'] }}</b><span>{{ $featured['name'] }} · {{ $featured['tag'] }}</span></span>
                <x-site.icon name="arrow" size="20" />
            </a>
        @endif
        @if ($directory['groups'] !== [])
            <nav class="s-tdir-all" aria-label="{{ $ix['directory'] }}">
                @foreach ($directory['groups'] as $group)
                    <div class="s-tdir-grp s-v{{ $group['colour'] }}">
                        <h3><i aria-hidden="true"></i>{{ $ix['groups'][$group['key']] }}</h3>
                        <ul>
                            @foreach ($group['tools'] as $tool)
                                <li><a href="{{ $tool['url'] }}"><span class="s-who-ic" aria-hidden="true"><x-site.icon :name="$tool['icon']" size="18" /></span><span><b>{{ $tool['name'] }}</b><span>{{ $tool['tag'] }}</span></span></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        @endif
    </section>
@endif
