{{-- The mudeo email's unsubscribe: one button, or that it's done (a plain form: the signed route
     needs no token). --}}
@extends('public.layout', ['title' => __('mudeo.unsubscribe.title').' | '.__('meta.brand')])

@section('content')
    @php($done_line = __('mudeo.unsubscribe.done', ['email' => (string) $email]))
    @php($ask_line = __('mudeo.unsubscribe.ask', ['email' => (string) $email]))
    @if ($email === null)
        <x-site.notice :headline="__('mudeo.unsubscribe.title')" :body="__('mudeo.unsubscribe.test')" />
    @elseif ($done)
        <x-site.notice :headline="__('mudeo.unsubscribe.done_title')" :body="$done_line" />
    @else
        <x-site.notice :headline="__('mudeo.unsubscribe.title')" :body="$ask_line">
            <form method="post" action="{{ $action }}">
                <button type="submit" class="btn-primary btn-lg">{{ __('mudeo.unsubscribe.button') }}</button>
            </form>
        </x-site.notice>
    @endif
@endsection
