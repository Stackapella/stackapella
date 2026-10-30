{{-- Something to try now, without the app, taken from the course's own first lesson. --}}
<p class="cp-try-lead">{{ $page['try']['lead'] }}</p>
<ol class="cp-try-steps">
    @foreach ((array) $page['try']['steps'] as $step)
        <li><span class="cp-try-n" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $step }}</span></li>
    @endforeach
</ol>
<p class="cp-try-after">{{ $page['try']['after'] }}</p>
