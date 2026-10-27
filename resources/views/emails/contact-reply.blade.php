@php
    $site = rtrim(config('atglance.frontend_url'), '/');
    $settings = \App\Models\Setting::get(\App\Models\Setting::CONTACT);
    $socials = array_filter(\App\Filament\Pages\ContactSettings::SOCIALS, fn ($s, $key) => filled($settings[$key] ?? null), ARRAY_FILTER_USE_BOTH);
    // $message only exists while the email is being sent; previews and tests render without it.
    $embed = fn (string $path) => isset($message) ? $message->embed($path) : null;
    $logo = $embed(public_path('branding/atglance-logo.png'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Re: {{ $contact->subject }}</title>
</head>
<body style="margin:0; padding:0; background:#EEF1F4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#14171B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF1F4;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">

                {{-- Header --}}
                <tr>
                    <td style="background:#FFFFFF; border-radius:12px 12px 0 0; padding:24px 32px; border:1px solid #E6E9EE; border-bottom:0;">
                        <a href="{{ $site }}" style="text-decoration:none;">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="AtGlance" height="34" style="display:block; height:34px; width:auto; border:0;">
                            @else
                                <span style="font-size:22px; font-weight:700; color:#14171B; letter-spacing:-0.02em;">AtGlance</span>
                            @endif
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="height:4px; line-height:4px; font-size:0; background:#4CC9FF; background-image:linear-gradient(135deg, #71F7D4 0%, #4CC9FF 100%);">&nbsp;</td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="background:#FFFFFF; padding:32px; border:1px solid #E6E9EE; border-top:0; border-bottom:0;">
                        <p style="margin:0 0 20px; font-size:16px; line-height:1.6;">Hi {{ $contact->name }},</p>

                        <div style="font-size:15px; line-height:1.7; color:#14171B;">{!! nl2br(e($body)) !!}</div>

                        @foreach ($images as $path)
                            @php($src = $embed($path))
                            @if ($src)
                                <p style="margin:24px 0 0;">
                                    <img src="{{ $src }}" alt="Image {{ $loop->iteration }}" style="display:block; max-width:100%; height:auto; border:1px solid #E6E9EE; border-radius:8px;">
                                </p>
                            @endif
                        @endforeach

                        <p style="margin:28px 0 0; font-size:15px; line-height:1.6;">Thanks,<br>The AtGlance team</p>

                        {{-- Original message --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:32px; background:#F4F6F8; border-left:3px solid #2CB7D9; border-radius:6px;">
                            <tr>
                                <td style="padding:16px 20px; font-size:13px; line-height:1.6; color:#5B626B;">
                                    <div style="margin-bottom:6px; color:#8A9099;">
                                        Your message {{ $contact->reference() }} · {{ $contact->created_at->format('d M Y, H:i') }}
                                    </div>
                                    <div style="font-weight:600; color:#14171B; margin-bottom:6px;">{{ $contact->subject }}</div>
                                    <div>{!! nl2br(e(\Illuminate\Support\Str::limit($contact->message, 1500))) !!}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="background:#14171B; border-radius:0 0 12px 12px; padding:24px 32px; text-align:center; font-size:12px; line-height:1.7; color:#B7BEC6;">
                        <div style="margin-bottom:8px;">
                            <a href="{{ $site }}" style="color:#FFFFFF; text-decoration:none;">Website</a>
                            <span style="color:#5B626B;">&nbsp;|&nbsp;</span>
                            <a href="{{ $site }}/docs" style="color:#FFFFFF; text-decoration:none;">Docs</a>
                            <span style="color:#5B626B;">&nbsp;|&nbsp;</span>
                            <a href="{{ $site }}/known-problems" style="color:#FFFFFF; text-decoration:none;">Known problems</a>
                            <span style="color:#5B626B;">&nbsp;|&nbsp;</span>
                            <a href="{{ $site }}/contact" style="color:#FFFFFF; text-decoration:none;">Contact</a>
                        </div>
                        @if ($socials)
                            <div style="margin-bottom:8px;">
                                @foreach ($socials as $key => [$label])
                                    @unless ($loop->first)<span style="color:#5B626B;">&nbsp;·&nbsp;</span>@endunless
                                    <a href="{{ $settings[$key] }}" style="color:#71F7D4; text-decoration:none;">{{ $label }}</a>
                                @endforeach
                            </div>
                        @endif
                        <div>Reply to this email to continue the conversation. Reference: {{ $contact->reference() }}</div>
                        <div style="margin-top:8px; color:#8A9099;">© {{ date('Y') }} AtGlance · Self-hosted operations for SREs, inside your boundary.</div>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
