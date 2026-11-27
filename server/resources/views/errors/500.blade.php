{{-- A 500 in a browser (the API answers with its own shape): words only, never what happened inside,
     and a way on. --}}
@extends('public.layout', ['title' => __('errors.server.title').' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="__('errors.server.title')" :body="__('errors.server.body')">
        <p class="s-notice-row">
            <a href="/" class="btn-primary btn-lg">{{ __('errors.home') }}</a>
            <a href="{{ \App\Support\Http\Hosts::app('/') }}" class="btn-secondary btn-lg">{{ __('errors.app') }}</a>
        </p>
        {{-- An email, not the app's form: the app may be down too (docs/feedback-plan.md). --}}
        @php($email = (string) config('site.contact_email'))
        @php([$before, $after] = explode('EMAIL_AT', __('errors.email', ['email' => 'EMAIL_AT']), 2) + [1 => ''])
        <p class="s-notice-tell">{{ $before }}<a href="mailto:{{ $email }}" data-email-us>{{ $email }}</a>{{ $after }}</p>
    </x-site.notice>
@endsection
