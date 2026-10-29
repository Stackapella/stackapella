{{-- An alert to the admin (ops, second factor, sign-in): the title and its lines. --}}
@extends('emails.layout', ['because' => __('emails.because.admin')])

@section('body')
    @foreach ($lines as $line)
        <p class="sa-text" style="margin:0 0 12px;color:#211a15;">{{ $line }}</p>
    @endforeach
@endsection
