{{-- The shared demo slot (docs/demo-pages-plan.md, "Shared rules"; docs/design-rules.md, "Pictures and
     demos"): a live demo on an audience or feature page, built from the app's own code. A 16:9 stage
     showing the demo's still first frame (the slot), one big start over it (PRESS START in the game
     skin), and a caption under it. Nothing loads before the tap: the start loads the demo's module
     (`src`, under /js/public/demos/) and calls its mount(stage); `fit` 'content' lets a demo with its own
     height (the four-voice mixer) set the stage's, instead of 16:9; while it loads the button says so and
     can't be pressed again. Wired by js/public/demo-slot.js. --}}
@props(['src', 'caption', 'start', 'label', 'fit' => 'wide'])
<figure @class(['s-demo', 's-demo-fit' => $fit === 'content']) data-demo-src="{{ $src }}" data-state="idle" data-failed="{{ __('pages.site.demo_failed') }}">
    <div class="s-demo-stage" data-demo-stage aria-label="{{ $label }}" role="group">
        <div class="s-demo-frame" data-demo-frame>{{ $slot }}</div>
        <button type="button" class="s-demo-start" data-demo-start>
            <span class="s-demo-start-icon" aria-hidden="true"></span>
            <span class="s-demo-start-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$start" /></span>
            <span class="s-demo-start-wait" aria-hidden="true">{{ __('pages.site.demo_loading') }}</span>
        </button>
    </div>
    <figcaption class="s-demo-caption" data-demo-caption>{{ $caption }}</figcaption>
</figure>
<script type="module" src="/js/public/demo-slot.js"></script>
