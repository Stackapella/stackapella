{{-- What the app listens for and how a lesson is scored (stars in the game skin, the measure in the
     serious), beside two real screens of the course (web/tool/course_shots.py). --}}
<section class="s-ink" aria-labelledby="inside-h">
    <div class="s-wrap s-sec s-split cp-inside">
        <div>
            <h2 id="inside-h" class="s-title">{{ $page['titles']['inside'] }}</h2>
            <p class="s-lede">{{ $page['measures'] }}</p>
            <p class="cp-inside-score"><x-site.say :text="$page['scoring']" /></p>
        </div>
        <div class="s-course-shots">
            @foreach ((array) ($page['shots'] ?? []) as $alt)
                @php($shotId = 'course-'.$page['slug'].'--'.$loop->iteration)
                <x-site.appshot :id="$shotId" :alt="$alt" />
            @endforeach
        </div>
    </div>
</section>
