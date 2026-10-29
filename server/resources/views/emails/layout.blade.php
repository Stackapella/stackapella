{{-- The one layout for our email (account emails, admin alerts, the mudeo note): readable without
     images and with no tracking, in the brand: Warm's colours, light, and dark for mail apps that
     follow the device; the mark (four bars, one per part, on a black square) drawn as table cells;
     tables and inline styles so every mail app draws it the same. `heading` false leaves out the
     title, for a personal note. The footer says who it was sent to and why ($sentTo, $because),
     then the postal address. `$palette` (App\Support\Mail\EmailSkin) is the skin: serious (Warm,
     light and dark) unless the account email is to someone in game mode (the arcade cabinet,
     always dark, `$hud` over the heading). --}}
@php($p = $palette ?? \App\Support\Mail\EmailSkin::palette(\App\Support\Mail\EmailSkin::SERIOUS))
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    <style>
        @if ($p['skin'] === \App\Support\Mail\EmailSkin::SERIOUS)
        @media (prefers-color-scheme: dark) {
            .sa-page { background: #17120f !important; }
            .sa-card { background: #1e1814 !important; border-color: #352c26 !important; }
            .sa-inset { background: #17120f !important; border-color: #352c26 !important; }
            .sa-rule { border-color: #352c26 !important; }
            .sa-text, .sa-text a, .sa-text strong { color: #f4eee6 !important; }
            .sa-muted, .sa-muted a { color: #a1968b !important; }
        }
        @endif
        @media (max-width: 480px) {
            .sa-card { padding: 24px 20px 20px !important; }
        }
    </style>
</head>
<body class="sa-page" bgcolor="{{ $p['page'] }}" style="margin:0;padding:0;background:{{ $p['page'] }};">
    <table role="presentation" class="sa-page" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="{{ $p['page'] }}" style="background:{{ $p['page'] }};">
        <tr>
            <td align="center" style="padding:28px 12px 32px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
                    <tr>
                        <td style="padding:0 0 16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="28" height="28" style="width:28px;height:28px;background:#000000;border-radius:5px;padding:0;vertical-align:top;">
                                        {{-- The mark as the app's header draws it (web/js/logo-mark.js), at 28 px: each bar its own
                                             table, so each keeps its own width (rows of one table share the widest). --}}
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="28" style="width:28px;">
                                            <tr><td style="height:8px;font-size:0;line-height:8px;mso-line-height-rule:exactly;">&nbsp;</td></tr>
                                            <tr><td style="padding:0 0 0 4px;font-size:0;line-height:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="20" style="width:20px;"><tr><td width="20" height="2" style="width:20px;height:2px;background:#ff9139;border-radius:1px;font-size:0;line-height:2px;mso-line-height-rule:exactly;">&nbsp;</td></tr></table></td></tr>
                                            <tr><td style="height:1px;font-size:0;line-height:1px;mso-line-height-rule:exactly;">&nbsp;</td></tr>
                                            <tr><td style="padding:0 0 0 4px;font-size:0;line-height:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="15" style="width:15px;"><tr><td width="15" height="2" style="width:15px;height:2px;background:#ffea6b;border-radius:1px;font-size:0;line-height:2px;mso-line-height-rule:exactly;">&nbsp;</td></tr></table></td></tr>
                                            <tr><td style="height:1px;font-size:0;line-height:1px;mso-line-height-rule:exactly;">&nbsp;</td></tr>
                                            <tr><td style="padding:0 0 0 4px;font-size:0;line-height:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="17" style="width:17px;"><tr><td width="17" height="2" style="width:17px;height:2px;background:#45eca4;border-radius:1px;font-size:0;line-height:2px;mso-line-height-rule:exactly;">&nbsp;</td></tr></table></td></tr>
                                            <tr><td style="height:1px;font-size:0;line-height:1px;mso-line-height-rule:exactly;">&nbsp;</td></tr>
                                            <tr><td style="padding:0 0 0 4px;font-size:0;line-height:0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="12" style="width:12px;"><tr><td width="12" height="2" style="width:12px;height:2px;background:#968dfd;border-radius:1px;font-size:0;line-height:2px;mso-line-height-rule:exactly;">&nbsp;</td></tr></table></td></tr>
                                            <tr><td style="height:9px;font-size:0;line-height:9px;mso-line-height-rule:exactly;">&nbsp;</td></tr>
                                        </table>
                                    </td>
                                    <td class="sa-text" style="padding-left:10px;font-size:16px;font-weight:800;letter-spacing:-0.01em;color:{{ $p['text'] }};vertical-align:middle;">{{ __('emails.brand') }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="sa-card sa-text" bgcolor="{{ $p['card'] }}" style="background:{{ $p['card'] }};border:1px solid {{ $p['line'] }};border-radius:18px;padding:30px 30px 24px;font-family:{{ $p['body'] }};font-size:15px;line-height:1.65;color:{{ $p['text'] }};">
                            @if (! empty($hud))
                                <p style="margin:0 0 8px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11px;line-height:1.4;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:{{ $p['hud'] }};">{{ $hud }}</p>
                            @endif
                            @if ($heading ?? true)
                                <h1 class="sa-text" style="margin:0 0 14px;font-family:{{ $p['headingFont'] }};font-size:22px;line-height:1.3;font-weight:800;letter-spacing:{{ $p['skin'] === \App\Support\Mail\EmailSkin::GAME ? '0.02em' : '-0.01em' }};text-transform:{{ $p['headingCase'] }};color:{{ $p['heading'] }};">{{ $title }}</h1>
                            @endif
                            @yield('body')
                        </td>
                    </tr>
                    <tr>
                        <td class="sa-muted" style="padding:18px 4px 0;font-family:{{ $p['body'] }};font-size:12px;line-height:1.6;color:{{ $p['muted'] }};">
                            @hasSection('footer')
                                @yield('footer')
                            @else
                                {{ ! empty($sentTo) ? __('emails.sent_to', ['email' => $sentTo, 'because' => $because ?? __('emails.because.default')]) : __('emails.sent_you', ['because' => $because ?? __('emails.because.default')]) }}
                            @endif
                            @if (! empty($postal))
                                <br>{{ $postal }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
