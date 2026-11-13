{{-- The free tools' top (docs/tools-plan.md, "Tool page frame"): the site's own header, as on every page (Hillel,
     2026-11-08: "when using a tool make sure we show the header"), its logo leading to the landing tagged ?ref=tool-<tool>
     or ?ref=tools (or the page's own `ref`: the warm-ups' ?ref=warmups), so it's counted; then a slim tool bar under it
     with the page's trail (or its name) and the tool's key control. The light and dark switch is the header's, in the same
     place as on every page (Hillel, 2026-11-13: "the light/dark toggle is in the wrong place"); the bar has none. The hub
     is a tool page's trail, below the tool. --}}
@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))
<x-site.header :app-url="$links['app']" :home="'/?'.http_build_query(['ref' => is_string($ref ?? null) ? $ref : (is_string($toolSlug ?? null) ? 'tool-'.$toolSlug : 'tools')])" />
<div @class(['s-wrap s-tbrand', 's-tbrand-tool' => $onTool ?? false]) data-tool-bar>
    {{-- The page's trail back up (Hillel, 2026-11-08: "show breadcrumbs ... at the top of each tool"), "Tools / Tuner",
         or, on a page with no parent (the hub), its name in quiet text. --}}
    @if (is_array($trail ?? null))
        <x-site.crumbs :trail="$trail" class="s-tbrand-crumb" />
    @else
        <span class="s-tbrand-name">{{ ($onTool ?? false) && is_string($toolName ?? null) ? $toolName : $ix['eyebrow'] }}</span>
    @endif
    <div class="s-tbrand-end">
        @if ($onTool ?? false)
            {{-- The tool's key control (the tuner's A4 stepper, the metronome's metre), mounted here by its module. --}}
            <div class="s-tbrand-key" data-tool-key></div>
        @endif
    </div>
</div>
