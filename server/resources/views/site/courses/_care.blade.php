{{-- A designed course page's band on singing it safely, in its own words: a lead and a few points
     (the page's `$key` entry and its title), on the dark band. --}}
<section class="s-ink cp-care" aria-labelledby="{{ $key }}-h">
    <div class="s-wrap s-sec">
        <h2 id="{{ $key }}-h" class="s-title">{{ $page['titles'][$key] }}</h2>
        <p class="s-lede">{{ $page[$key]['lead'] }}</p>
        <ul class="cp-care-points">
            @foreach ((array) $page[$key]['points'] as $point)
                <li><x-site.icon name="info" size="18" /><span>{{ $point }}</span></li>
            @endforeach
        </ul>
    </div>
</section>
