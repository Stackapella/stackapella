{{-- Plain text: never HTML, so the words are printed as they are, not escaped. --}}
{!! $title !!}

@foreach ($lines as $line)
{!! $line !!}

@endforeach
---
{!! __('emails.sent_you', ['because' => __('emails.because.admin')]) !!}
