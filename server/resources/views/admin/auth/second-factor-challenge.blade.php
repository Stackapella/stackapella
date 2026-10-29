@extends('layouts.page', ['title' => __('admin.second_factor.challenge_title')])

@push('scripts')
    <script type="module" src="/assets/passkeys.js"></script>
@endpush

@section('content')
    <h1 class="mb-2 text-3xl font-bold">{{ __('admin.second_factor.challenge_title') }}</h1>
    <p class="mb-8 text-muted">{{ $sudo ? __('admin.second_factor.sudo_intro') : __('admin.second_factor.challenge_intro') }}</p>

    @if ($locked)
        <p class="mb-6 rounded-xl border border-record/60 bg-panel p-4" role="alert">{{ __('admin.second_factor.locked') }}</p>
    @endif

    @if ($hasPasskey)
        <section class="mb-10" data-passkey-verify
            data-options-url="{{ route('admin.second-factor.challenge.options') }}"
            data-verify-url="{{ route('admin.second-factor.challenge.passkey') }}"
            data-unsupported="{{ __('admin.second_factor.unsupported') }}"
            data-cancelled="{{ __('admin.second_factor.cancelled') }}"
            data-failed="{{ __('admin.second_factor.passkey_failed') }}">
            <button type="button" data-passkey-start class="w-full rounded-xl bg-cream px-5 py-3.5 font-semibold text-ink disabled:opacity-50">{{ __('admin.second_factor.passkey_use') }}</button>
            <p data-passkey-error class="mt-3 text-record" role="alert"></p>
        </section>
    @endif

    @if ($hasTotp)
        <form method="post" action="{{ route('admin.second-factor.challenge.code') }}">
            @csrf
            <label for="code" class="mb-1 block font-semibold">{{ __('admin.second_factor.code_or_recovery_label') }}</label>
            <input id="code" name="code" autocomplete="one-time-code" required
                class="mb-2 w-full rounded-xl border border-line bg-panel px-4 py-3 text-lg"
                @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            @error('code')
                <p id="code-error" class="mb-2 text-record">{{ $message }}</p>
            @enderror
            <button type="submit" class="mt-2 w-full rounded-xl border border-line px-5 py-3.5 font-semibold">{{ __('admin.second_factor.code_submit') }}</button>
        </form>
    @endif

    <form method="post" action="{{ route('admin.sign-out') }}" class="mt-10">
        @csrf
        <button type="submit" class="text-muted underline">{{ __('admin.sign_out') }}</button>
    </form>
@endsection
