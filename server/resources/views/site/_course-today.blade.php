{{-- What works today, for a course still coming: the free course, beside the real course map. --}}
<section class="s-ink" aria-labelledby="today-h">
    <div class="s-wrap s-split">
        <div>
            <p class="s-kick">{{ __('pages.site.course_today_kick') }}</p>
            <h2 id="today-h" class="s-title">{{ __('pages.site.course_today_title') }}</h2>
            <p class="s-lede">{{ __('pages.site.course_today_body') }}</p>
            <p class="s-actions-l"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a></p>
        </div>
        <div class="s-card3d s-heroshot"><x-site.appshot id="your-voice--map" :alt="__('site.features.pages.course.shot_alt')" /></div>
    </div>
</section>
