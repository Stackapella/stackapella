@extends('layouts.page', ['title' => __('admin.sign_in.title')])

@section('content')
    <h1 class="mb-2 text-3xl font-bold">{{ __('admin.sign_in.title') }}</h1>
    <p class="mb-8 text-muted">{{ __('admin.sign_in.intro') }}</p>

    @if ($ended)
        <p class="mb-6 rounded-xl border border-line bg-panel p-4" role="status">{{ __('admin.sign_in.ended') }}</p>
    @endif

    @if (session('status') === 'signed_out')
        <p class="mb-6 rounded-xl border border-line bg-panel p-4" role="status">{{ __('admin.sign_in.signed_out') }}</p>
    @endif

    @if ($error !== null)
        <p class="mb-6 rounded-xl border border-record/60 bg-panel p-4" role="alert">{{ __('admin.sign_in.errors.'.$error) }}</p>
    @endif

    @if (is_string($yourGoogleId ?? null))
        <div class="mb-6 rounded-xl border border-line bg-panel p-4" role="status">
            <p class="mb-2">{{ __('admin.sign_in.your_google_id') }}</p>
            <p class="font-mono text-lg"><code>{{ $yourGoogleId }}</code></p>
        </div>
    @endif

    <form method="post" action="{{ route('auth.provider', 'google') }}">
        @csrf
        <input type="hidden" name="intended" value="{{ $intended }}">
        <div class="hidden" aria-hidden="true">
            <label for="nickname">{{ __('forms.honeypot') }}</label>
            <input type="text" id="nickname" name="nickname" tabindex="-1" autocomplete="off">
        </div>
        <button type="submit" class="w-full rounded-xl bg-cream px-5 py-3.5 font-semibold text-ink">
            {{ __('admin.sign_in.google') }}
        </button>
    </form>
@endsection
