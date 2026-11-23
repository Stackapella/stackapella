{{-- The director's view of a choir group's song, as a live demo (js/public/demos/choir-board.js): its still frame,
     one start over it, a caption. Made-up singers, never faces. Included by the demo slot (`kind` choir) and by For
     choir directors' first screen, where it is the page's picture (the consultant's concept, 2026-11-23).
     Takes $caption, $start and $label; $private false leaves out its privacy line when the caption already says it. --}}
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
<x-site.demo src="/js/public/demos/choir-board.js" fit="content" :caption="$caption" :start="$start" :label="$label">
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
        @if ($private ?? true)
            <p class="s-gb-private">{{ $cd['private'] }}</p>
        @endif
    </div>
</x-site.demo>
