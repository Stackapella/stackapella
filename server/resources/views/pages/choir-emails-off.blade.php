{{-- The choir-ready email's one-click way out (Hear yourself as a choir): one button to turn them off, or that they're
     off (a plain form: the signed route needs no token). The same page as the ideas one, with own_choir's words. --}}
@extends('public.layout', ['title' => __('own_choir.email_off.title').' | '.__('meta.brand')])

@section('content')
    @if ($done)
        <x-site.notice :headline="__('own_choir.email_off.done_title')" :body="__('own_choir.email_off.done')" />
    @else
        <x-site.notice :headline="__('own_choir.email_off.title')" :body="__('own_choir.email_off.ask')">
            <form method="post" action="{{ $action }}">
                <button type="submit" class="btn-primary btn-lg">{{ __('own_choir.email_off.button') }}</button>
            </form>
        </x-site.notice>
    @endif
@endsection
