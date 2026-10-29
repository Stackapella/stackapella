{{-- Hillel's preview link: one button, so a link preview never counts as a visit (a plain form:
     the route needs no token). --}}
@extends('public.layout', ['title' => __('meta.preview.title').' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="__('meta.preview.title')" :body="__('meta.preview.body')">
        <form method="post" action="{{ $action }}">
            <button type="submit" class="btn-primary btn-lg">{{ __('meta.preview.button') }}</button>
        </form>
    </x-site.notice>
@endsection
