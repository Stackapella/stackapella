{{-- Ideas' emails to their writer: one button to turn them off, or that they're off (a plain form:
     the signed route needs no token). --}}
@extends('public.layout', ['title' => __('ideas.unsubscribe.title').' | '.__('meta.brand')])

@section('content')
    @if ($done)
        <x-site.notice :headline="__('ideas.unsubscribe.done_title')" :body="__('ideas.unsubscribe.done')" />
    @else
        <x-site.notice :headline="__('ideas.unsubscribe.title')" :body="__('ideas.unsubscribe.ask')">
            <form method="post" action="{{ $action }}">
                <button type="submit" class="btn-primary btn-lg">{{ __('ideas.unsubscribe.button') }}</button>
            </form>
        </x-site.notice>
    @endif
@endsection
