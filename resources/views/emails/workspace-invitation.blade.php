<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>You've been invited to {{ $workspaceName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:40px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 40px 16px 40px;">
                            <h1 style="margin:0 0 8px 0;font-size:22px;font-weight:600;color:#111827;">You've been invited to Taskline</h1>
                            <p style="margin:0;color:#6b7280;font-size:14px;line-height:1.5;">
                                <strong style="color:#111827;">{{ $inviterName }}</strong> has invited you to join
                                <strong style="color:#111827;">{{ $workspaceName }}</strong> as a <strong>{{ ucfirst($role) }}</strong>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 40px 32px 40px;">
                            <a href="{{ $acceptUrl }}"
                               style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;">
                                Accept invitation
                            </a>
                            <p style="margin:24px 0 0 0;font-size:12px;color:#9ca3af;line-height:1.5;">
                                Or paste this link into your browser:<br>
                                <span style="color:#4f46e5;word-break:break-all;">{{ $acceptUrl }}</span>
                            </p>
                            @if($expiresAt)
                                <p style="margin:16px 0 0 0;font-size:12px;color:#9ca3af;">
                                    This invitation expires on {{ $expiresAt->format('M j, Y \a\t g:i A') }}.
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0 0;font-size:11px;color:#9ca3af;">
                    If you weren't expecting this invitation, you can safely ignore this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
