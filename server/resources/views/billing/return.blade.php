@extends('layouts.page', ['title' => $done ? __('billing.return.done_title') : __('billing.return.waiting_title')])

@section('content')
    <h1 class="mb-3 text-3xl font-bold">{{ $done ? __('billing.return.done_title') : __('billing.return.waiting_title') }}</h1>
    <p class="mb-6 text-lg">{{ $done ? __('billing.return.done_body') : __('billing.return.waiting_body') }}</p>
    <p><a class="font-semibold underline" href="/settings/plus">{{ __('billing.return.back') }}</a></p>
@endsection
