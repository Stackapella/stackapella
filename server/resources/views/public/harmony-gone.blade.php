{{-- A /h/<id> that isn't there (404) or was removed (410): said plainly, with the way to make your own. A choir from the
     choir gallery says so in the gallery's words (docs/choir-gallery-plan.md, 2): removed, "This choir isn't in the
     gallery any more", whoever removed it; held after a report, "Not available right now"; each with the gallery's way
     back and making your own. --}}
@php($gallery = in_array($reason, ['gallery_removed', 'gallery_held'], true))
@php($headline = match ($reason) { 'gallery_removed' => __('site.choir_gallery.removed'), 'gallery_held' => __('site.choir_gallery.held'), 'removed' => __('sharing.page.removed'), default => __('sharing.page.missing') })
@extends('public.layout', ['title' => $headline.' | '.__('meta.brand')])

@section('content')
    @if ($gallery)
        <x-site.notice :headline="$headline" :body="__('site.choir_gallery.make_line')">
            <a href="{{ $links['gallery'] }}" class="btn-primary btn-lg">{{ __('site.choir_gallery.see') }}</a>
            <a href="/{{ \App\Support\Pages\YourChoirStage::SLUGS['en'] }}" class="btn-secondary btn-lg">{{ __('site.choir_gallery.make') }}</a>
        </x-site.notice>
    @else
        <x-site.notice :headline="$headline" :body="$reason === 'removed' ? __('sharing.page.removed_body') : __('sharing.page.missing_body')">
            <a href="{{ $links['app'] }}" class="btn-primary btn-lg">{{ __('sharing.page.make') }}</a>
        </x-site.notice>
    @endif
@endsection
