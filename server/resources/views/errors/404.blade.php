{{-- A 404 in a browser (the API answers with its own shape): words only, never what happened inside,
     and a way on, and a way to tell us. --}}
@extends('public.layout', ['title' => __('errors.not_found.title').' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="__('errors.not_found.title')" :body="__('errors.not_found.body')">
        <p class="s-notice-row">
            <a href="/" class="btn-primary btn-lg">{{ __('errors.home') }}</a>
            <a href="{{ \App\Support\Http\Hosts::app('/') }}" class="btn-secondary btn-lg">{{ __('errors.app') }}</a>
        </p>
        <x-site.tell-us />
    </x-site.notice>
@endsection
