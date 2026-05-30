<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#fafaf9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1a1a17;">
  <div style="max-width:440px;margin:40px auto;background:#ffffff;border:1px solid #e7e7e2;border-radius:12px;overflow:hidden;">
    <div style="height:4px;background:#4f46e5;"></div>
    <div style="padding:32px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
        <span style="display:inline-block;width:32px;height:32px;border-radius:7px;background:#4f46e5;color:#fff;font-size:15px;font-weight:700;text-align:center;line-height:32px;">T</span>
        <span style="font-size:16px;font-weight:600;">Taskline</span>
      </div>
      <h1 style="font-size:20px;font-weight:600;margin:0 0 8px;">Verify your email</h1>
      <p style="font-size:14px;color:#6b6b65;line-height:1.5;margin:0 0 24px;">
        @if($name)Hi {{ $name }} — @endif Enter this 6-digit code to finish setting up your account. It expires in 15 minutes.
      </p>
      <div style="text-align:center;margin:0 0 24px;">
        <span style="display:inline-block;font-family:'JetBrains Mono',ui-monospace,Menlo,monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#1a1a17;background:#f4f4f2;border:1px solid #e7e7e2;border-radius:8px;padding:14px 22px;">{{ $code }}</span>
      </div>
      <p style="font-size:12px;color:#9a9a93;line-height:1.5;margin:0;">
        If you didn't create a Taskline account, you can safely ignore this email.
      </p>
    </div>
  </div>
</body>
</html>
