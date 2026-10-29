{{-- The app's host before launch, for anyone without the beta pass, and a preview link that's
     paused or replaced: what Stackapella is, how invited singers get in, and the public site. --}}
@extends('public.layout', ['title' => __('meta.coming_soon.title').' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="__('meta.coming_soon.title')" :body="__('meta.coming_soon.body')">
        <p class="s-sub">{{ __('meta.coming_soon.invited') }}</p>
        <p class="s-notice-row">
            <a href="{{ \App\Support\Http\Hosts::public('/') }}" class="btn-primary btn-lg">{{ __('meta.coming_soon.more') }}</a>
            {{-- Signing in gives the beta pass, so the app opens at / from then on. --}}
            <a href="{{ \App\Support\Http\Hosts::app('/signin') }}" class="btn-secondary btn-lg">{{ __('meta.coming_soon.sign_in') }}</a>
        </p>
    </x-site.notice>
@endsection
