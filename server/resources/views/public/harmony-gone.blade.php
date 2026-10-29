{{-- A /h/<id> that isn't there (404) or was removed (410): said plainly, with the way to make your
     own. --}}
@extends('public.layout', ['title' => ($reason === 'removed' ? __('sharing.page.removed') : __('sharing.page.missing')).' | '.__('meta.brand')])

@section('content')
    <x-site.notice :headline="$reason === 'removed' ? __('sharing.page.removed') : __('sharing.page.missing')" :body="$reason === 'removed' ? __('sharing.page.removed_body') : __('sharing.page.missing_body')">
        <a href="{{ $links['app'] }}" class="btn-primary btn-lg">{{ __('sharing.page.make') }}</a>
    </x-site.notice>
@endsection
