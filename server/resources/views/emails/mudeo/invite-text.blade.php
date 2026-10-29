{{-- Plain text: never HTML, so the words are printed as they are, not escaped. --}}
{!! $hello !!}

@foreach ($paragraphs as $line)
{!! \App\Support\Mail\Bold::plain($line) !!}

@endforeach
@foreach ($points as $point)
- {!! \App\Support\Mail\Bold::plain($point) !!}
@endforeach

@foreach ($beforeInvite as $line)
{!! \App\Support\Mail\Bold::plain($line) !!}

@endforeach
{!! __('mudeo.mail.invited') !!}
{!! __('mudeo.mail.invite') !!}
{!! __('mudeo.mail.button') !!}: {!! $link !!}
{!! __('mudeo.mail.card_line') !!}

@foreach ($after as $line)
{!! \App\Support\Mail\Bold::plain($line) !!}

@endforeach
{!! __('mudeo.mail.signature') !!}

@foreach ($postscript as $line)
{!! __('mudeo.mail.ps') !!} {!! \App\Support\Mail\Bold::plain($line) !!}

@endforeach
---
{!! $footer[0] !!}{!! __('mudeo.mail.unsubscribe_link') !!}: {!! $unsubscribe !!}{!! $footer[1] !!}
{!! $postal !!}
