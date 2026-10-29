{{-- The one email to mudeo's users, as Hillel laid it out: a personal letter (no title), what
     Stackapella does as bold-lead rows, the invitation in its own box with the button, the
     sign-off, and the P.S. A phrase between "*" in its words is bold. Its own footer: who it went
     to and why, that it's the only one, the one-click unsubscribe and the postal address. --}}
@extends('emails.layout', ['title' => __('mudeo.mail.subject'), 'heading' => false])
@php($p = $palette ?? \App\Support\Mail\EmailSkin::palette(\App\Support\Mail\EmailSkin::SERIOUS))

@section('body')
    <p class="sa-text" style="margin:0 0 14px;color:{{ $p['text'] }};">{{ $hello }}</p>
    @foreach ($paragraphs as $line)
        <p class="sa-text" style="margin:0 0 14px;color:{{ $p['text'] }};">@include('emails._words', ['line' => $line])</p>
    @endforeach
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 18px;">
        @foreach ($points as $i => $point)
            <tr>
                <td class="sa-text sa-rule" style="padding:11px 0;{{ $i > 0 ? 'border-top:1px solid '.$p['line'].';' : '' }}font-size:15px;line-height:1.55;color:{{ $p['text'] }};">@include('emails._words', ['line' => $point])</td>
            </tr>
        @endforeach
    </table>
    @foreach ($beforeInvite as $line)
        <p class="sa-text" style="margin:0 0 16px;color:{{ $p['text'] }};">@include('emails._words', ['line' => $line])</p>
    @endforeach
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 20px;">
        <tr>
            <td class="sa-inset" style="background:{{ $p['inset'] }};border:1px solid {{ $p['line'] }};border-radius:14px;padding:20px 20px 16px;">
                <p class="sa-text" style="margin:0 0 6px;font-size:17px;font-weight:800;color:{{ $p['text'] }};">{{ __('mudeo.mail.invited') }}</p>
                <p class="sa-text" style="margin:0 0 14px;font-size:15px;color:{{ $p['text'] }};">{{ __('mudeo.mail.invite') }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 10px;">
                    <tr>
                        <td bgcolor="{{ $p['button'] }}" style="background:{{ $p['button'] }};border-radius:999px;">
                            <a href="{{ $link }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:700;line-height:1.2;color:{{ $p['buttonText'] }};text-decoration:none;border-radius:999px;">{{ __('mudeo.mail.button') }}</a>
                        </td>
                    </tr>
                </table>
                <p class="sa-muted" style="margin:0 0 6px;font-size:13px;color:{{ $p['muted'] }};">{{ __('mudeo.mail.card_line') }}</p>
                <p class="sa-muted" style="margin:0;font-size:12px;line-height:1.5;color:{{ $p['muted'] }};word-break:break-all;">{{ __('emails.paste') }} <a href="{{ $link }}" style="color:{{ $p['link'] }};">{{ $link }}</a></p>
            </td>
        </tr>
    </table>
    @foreach ($after as $line)
        <p class="sa-text" style="margin:0 0 14px;color:{{ $p['text'] }};">@include('emails._words', ['line' => $line])</p>
    @endforeach
    <p class="sa-text" style="margin:0 0 4px;color:{{ $p['text'] }};">{{ __('mudeo.mail.signature') }}</p>
    @foreach ($postscript as $line)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0 0;">
            <tr><td class="sa-rule" style="border-top:1px solid {{ $p['line'] }};font-size:0;line-height:0;">&nbsp;</td></tr>
        </table>
        <p class="sa-text" style="margin:14px 0 0;font-size:14px;color:{{ $p['text'] }};">@include('emails._words', ['line' => __('mudeo.mail.ps').' '.$line])</p>
    @endforeach
@endsection

@section('footer')
    {{ $footer[0] }}<a href="{{ $unsubscribe }}" style="color:{{ $p['muted'] }};">{{ __('mudeo.mail.unsubscribe_link') }}</a>{{ $footer[1] }}
@endsection
