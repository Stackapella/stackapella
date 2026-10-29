{{-- Too many tries in a short time: when to try again, and a way on. --}}
@extends('public.layout', ['title' => __('errors.rate_limited.title').' | '.__('meta.brand')])

@section('content')
    @php($wait = trans_choice('errors.rate_limited.body', $minutes ?? 1, ['minutes' => $minutes ?? 1]))
    <x-site.notice :headline="__('errors.rate_limited.title')" :body="$wait">
        <a href="/" class="btn-primary btn-lg">{{ __('errors.home') }}</a>
    </x-site.notice>
@endsection
