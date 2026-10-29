{{-- A 503 in a browser (the API answers with its own shape): words only, never what happened inside,
     and a way on. --}}
@extends('public.layout', ['title' => __('errors.maintenance.title').' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="__('errors.maintenance.title')" :body="__('errors.maintenance.body')">
        <a href="/" class="btn-primary btn-lg">{{ __('errors.home') }}</a>
        <a href="{{ \App\Support\Http\Hosts::app('/') }}" class="btn-secondary btn-lg">{{ __('errors.app') }}</a>
    </x-site.notice>
@endsection
