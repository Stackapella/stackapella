@extends('layouts.page', ['title' => __('dev.login.title')])

@section('content')
    <h1 class="mb-2 text-3xl font-bold">{{ __('dev.login.title') }}</h1>
    <p class="mb-8 text-muted">{{ __('dev.login.intro') }}</p>

    <ul class="space-y-2">
        @forelse ($users as $user)
            <li>
                <form method="post" action="{{ route('dev.login.store') }}">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                    <input type="hidden" name="intended" value="{{ $intended }}">
                    <button type="submit" class="w-full rounded-xl border border-line bg-panel px-4 py-3 text-start" data-dev-login="{{ $user->email }}">
                        <span class="block font-semibold">{{ $user->name }}</span>
                        <span class="block text-sm text-muted">{{ $user->email }} · {{ $user->is_admin ? __('enums.admin_role.admin') : ($user->is_editor ? __('enums.admin_role.editor') : __('dev.login.no_role')) }}</span>
                    </button>
                </form>
            </li>
        @empty
            <li class="text-muted">{{ __('dev.login.empty') }}</li>
        @endforelse
    </ul>
@endsection
