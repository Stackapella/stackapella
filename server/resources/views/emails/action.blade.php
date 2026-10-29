{{-- An account email (AccountMail): a few lines, one button, a small line under it, the link
     written out for when the button doesn't work, and a note for anyone who didn't expect it. --}}
@extends('emails.layout')
@php($p = $palette ?? \App\Support\Mail\EmailSkin::palette(\App\Support\Mail\EmailSkin::SERIOUS))

@section('body')
    @if (! empty($hello))
        <p class="sa-text" style="margin:0 0 12px;color:{{ $p['text'] }};">{{ $hello }}</p>
    @endif
    @foreach ($lines as $line)
        <p class="sa-text" style="margin:0 0 12px;color:{{ $p['text'] }};">@include('emails._words', ['line' => $line])</p>
    @endforeach
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 10px;">
        <tr>
            <td bgcolor="{{ $p['button'] }}" style="background:{{ $p['button'] }};border-radius:999px;">
                <a href="{{ $url }}" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:700;line-height:1.2;color:{{ $p['buttonText'] }};text-decoration:none;border-radius:999px;">{{ $button }}</a>
            </td>
        </tr>
    </table>
    @if (! empty($soon))
        <p class="sa-muted" style="margin:0 0 18px;font-size:13px;color:{{ $p['muted'] }};">{{ $soon }}</p>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 4px;">
        <tr>
            <td class="sa-inset" bgcolor="{{ $p['inset'] }}" style="background:{{ $p['inset'] }};border:1px solid {{ $p['line'] }};border-radius:12px;padding:12px 14px;">
                <p class="sa-muted" style="margin:0 0 4px;font-size:13px;line-height:1.5;color:{{ $p['muted'] }};">{{ __('emails.paste') }}</p>
                <p class="sa-text" style="margin:0;font-size:13px;line-height:1.5;word-break:break-all;"><a href="{{ $url }}" style="color:{{ $p['link'] }};">{{ $url }}</a></p>
            </td>
        </tr>
    </table>
    @if (! empty($note))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 0;">
            <tr><td class="sa-rule" style="border-top:1px solid {{ $p['line'] }};font-size:0;line-height:0;">&nbsp;</td></tr>
        </table>
        <p class="sa-text" style="margin:14px 0 0;font-size:14px;color:{{ $p['text'] }};">@if (! empty($note['lead']))<strong style="font-weight:700;">{{ $note['lead'] }}</strong> @endif{{ $note['text'] }}</p>
    @endif
@endsection
