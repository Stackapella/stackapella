{{-- A song, guide, feature or legal page that isn't there (404): said plainly, with the way on
     (`links.app`, the nearest list) and back to the start. --}}
@extends('public.layout', ['title' => __('errors.not_found.title').' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="__('errors.not_found.title')" :body="__('errors.not_found.body')">
        <p class="s-notice-row">
            <a href="{{ $links['app'] }}" class="btn-primary btn-lg">{{ __('errors.onward') }}</a>
            <a href="/" class="btn-secondary btn-lg">{{ __('errors.home') }}</a>
        </p>
    </x-site.notice>
@endsection
