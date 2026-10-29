{{-- A /coach/<handle> that isn't there, for any reason (404): said plainly, with the way on. --}}
@extends('public.layout', ['title' => __('pages.coach.gone').' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="__('pages.coach.gone')" :body="__('pages.coach.gone_body')">
        <a href="{{ $links['app'] }}" class="btn-primary btn-lg">{{ __('errors.home') }}</a>
    </x-site.notice>
@endsection
