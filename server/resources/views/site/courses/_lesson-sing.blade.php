{{-- A lesson's What you sing on its page (site/lesson): its numbered steps, each line whole. --}}
<h3 class="mt-6 font-bold">{{ __('site.lessons.sing') }}</h3>
<ol class="s-tool-how">
    @foreach ($sing as $line)
        <li>{{ $line }}</li>
    @endforeach
</ol>
