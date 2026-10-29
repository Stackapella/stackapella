@extends('layouts.page', ['title' => __('admin.second_factor.setup_title')])

@push('scripts')
    <script type="module" src="/assets/passkeys.js"></script>
@endpush

@section('content')
    @if (is_array($recoveryCodes))
        <h1 class="mb-2 text-3xl font-bold">{{ __('admin.second_factor.codes_title') }}</h1>
        <p class="mb-6 text-muted">{{ __('admin.second_factor.codes_intro') }}</p>

        <ul class="mb-8 grid grid-cols-2 gap-2 font-mono text-lg" aria-label="{{ __('admin.second_factor.codes_title') }}">
            @foreach ($recoveryCodes as $code)
                <li class="rounded-lg bg-panel px-3 py-2 text-center">{{ $code }}</li>
            @endforeach
        </ul>

        <form method="post" action="{{ route('admin.second-factor.finish') }}">
            @csrf
            <button type="submit" class="w-full rounded-xl bg-cream px-5 py-3.5 font-semibold text-ink">{{ __('admin.second_factor.codes_saved') }}</button>
        </form>
    @else
        <h1 class="mb-2 text-3xl font-bold">{{ __('admin.second_factor.setup_title') }}</h1>
        <p class="mb-8 text-muted">{{ __('admin.second_factor.setup_intro') }}</p>

        <section class="mb-10" data-passkey-register
            data-options-url="{{ route('admin.second-factor.passkey.options') }}"
            data-store-url="{{ route('admin.second-factor.passkey.store') }}"
            data-unsupported="{{ __('admin.second_factor.unsupported') }}"
            data-cancelled="{{ __('admin.second_factor.cancelled') }}"
            data-failed="{{ __('admin.second_factor.passkey_not_saved') }}">
            <h2 class="mb-2 text-xl font-semibold">{{ __('admin.second_factor.passkey_heading') }}</h2>
            <p class="mb-4 text-muted">{{ __('admin.second_factor.passkey_intro') }}</p>
            <button type="button" data-passkey-start class="w-full rounded-xl bg-cream px-5 py-3.5 font-semibold text-ink disabled:opacity-50">{{ __('admin.second_factor.passkey_add') }}</button>
            <p data-passkey-error class="mt-3 text-record" role="alert"></p>
        </section>

        <section>
            <h2 class="mb-2 text-xl font-semibold">{{ __('admin.second_factor.totp_heading') }}</h2>
            <p class="mb-4 text-muted">{{ __('admin.second_factor.totp_intro') }}</p>
            <div class="mb-4 inline-block rounded-xl bg-white p-3">{!! $qr !!}</div>
            <p class="mb-4 break-all font-mono text-sm text-muted">{{ __('admin.second_factor.totp_secret', ['secret' => $secret]) }}</p>

            <form method="post" action="{{ route('admin.second-factor.totp') }}">
                @csrf
                <label for="code" class="mb-1 block font-semibold">{{ __('admin.second_factor.code_label') }}</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                    class="mb-2 w-full rounded-xl border border-line bg-panel px-4 py-3 text-lg"
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code')
                    <p id="code-error" class="mb-2 text-record">{{ $message }}</p>
                @enderror
                <button type="submit" class="mt-2 w-full rounded-xl border border-line px-5 py-3.5 font-semibold">{{ __('admin.second_factor.totp_confirm') }}</button>
            </form>
        </section>
    @endif
@endsection
