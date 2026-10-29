{{-- An /embed/h/<id> that isn't there (404) or was removed (410): one line, in the frame. --}}
@extends('public.layout', ['title' => $reason === 'removed' ? __('sharing.page.removed') : __('sharing.page.missing')])

@section('content')
    <main class="flex min-h-dvh items-center justify-center p-4 text-center">
        <p class="text-muted">{{ $reason === 'removed' ? __('sharing.page.removed') : __('sharing.page.missing') }}</p>
    </main>
@endsection
