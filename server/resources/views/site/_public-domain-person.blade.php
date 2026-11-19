{{-- One writer on the public domain page (site/public-domain.blade.php): their songs first, each with its part in it and
     linked where it's on our site, with where its state differs (a co-writer who died later, a country with a longer
     term); then the writer, their dates small after the name, and the dates' source (Hillel, 2026-11-19: "I recognize the
     song names more than the authors"). The words are App\Support\Pages\PublicDomain's. Written tight, without indents:
     the page lists a few hundred writers, and its weight is its own. --}}
<li class="s-pd-person" id="{{ $person['key'] }}" data-pd-person data-pd-state="{{ $person['state'] }}" data-pd-site="{{ $person['library'] ? '1' : '0' }}" data-pd-text="{{ $person['search'] }}"><ul class="s-pd-works">
@foreach ($person['works'] as $work)
<li><p class="s-pd-work">
@if ($work['url'] !== null)
<a href="{{ $work['url'] }}" class="s-pd-song" translate="no">{{ $work['title'] }}</a>
@else
<span class="s-pd-title">{{ $work['title'] }}</span>
@endif
@foreach ($work['roles'] as $role)
<span class="s-pd-role">{{ $role }}</span>
@endforeach
</p>
@foreach ($work['notes'] as $note)
@if ($note['more'] === [])
<p class="s-pd-note s-pd-dot s-pd-dot-{{ $note['kind'] }}">{{ $note['text'] }}</p>
@else
<details class="s-pd-note s-pd-more s-pd-dot s-pd-dot-{{ $note['kind'] }}"><summary>{{ $note['text'] }}</summary><p>{{ implode(', ', $note['more']) }}</p></details>
@endif
@endforeach
</li>
@endforeach
</ul><p class="s-pd-who"><span class="s-pd-name">{{ $person['name'] }}</span> <span class="s-pd-life">{{ $person['life'] }}</span>
@if ($person['source'] !== null)
<a href="{{ $person['source'] }}" class="s-pd-src" rel="noopener nofollow" target="_blank" aria-label="{{ $person['sourceLabel'] }}">{{ __('site.public_domain.source') }}</a>
@endif
</p></li>
