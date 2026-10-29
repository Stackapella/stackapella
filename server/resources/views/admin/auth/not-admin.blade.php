@extends('layouts.page', ['title' => __('admin.not_admin.title')])

@section('content')
    <h1 class="mb-2 text-3xl font-bold">{{ __('admin.not_admin.title') }}</h1>
    <p class="mb-8 text-muted">{{ __('admin.not_admin.body') }}</p>

    <form method="post" action="{{ route('admin.sign-out') }}">
        @csrf
        <button type="submit" class="w-full rounded-xl border border-line px-5 py-3.5 font-semibold">{{ __('admin.sign_out') }}</button>
    </form>
@endsection
