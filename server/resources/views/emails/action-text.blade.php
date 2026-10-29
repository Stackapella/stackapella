{{-- Plain text: never HTML, so the words are printed as they are, not escaped. --}}
{!! $title !!}

@if (! empty($hello))
{!! $hello !!}

@endif
@foreach ($lines as $line)
{!! \App\Support\Mail\Bold::plain($line) !!}

@endforeach
{!! $button !!}: {!! $url !!}
@if (! empty($soon))
{!! $soon !!}
@endif

@if (! empty($note))
{!! trim(($note['lead'] ?? '').' '.$note['text']) !!}

@endif
---
{!! ! empty($sentTo) ? __('emails.sent_to', ['email' => $sentTo, 'because' => $because ?? __('emails.because.default')]) : __('emails.sent_you', ['because' => $because ?? __('emails.because.default')]) !!}
@if (! empty($postal))
{!! $postal !!}
@endif
