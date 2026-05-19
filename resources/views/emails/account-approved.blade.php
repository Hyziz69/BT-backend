<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Approved</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f2f5; font-family: 'Helvetica Neue', Arial, sans-serif; color: #333; }
        .wrapper { max-width: 560px; margin: 40px auto; padding: 0 16px 40px; }
        .card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }

        /* Header */
        .header { background: #0f1117; padding: 32px 40px; text-align: center; }
        .header-icon { font-size: 2rem; color: #6ee7b7; display: block; margin-bottom: 12px; }
        .header h1 { color: #fff; font-size: 1.3rem; font-weight: 700; letter-spacing: 0.02em; }

        /* Body */
        .body { padding: 36px 40px; }
        .greeting { font-size: 1.1rem; font-weight: 600; color: #0f1117; margin-bottom: 12px; }
        .text { font-size: 0.95rem; color: #6b7280; line-height: 1.7; margin-bottom: 20px; }

        /* Status badge */
        .badge-row { display: flex; align-items: center; gap: 10px; background: #f0fdf4; border: 1px solid #6ee7b7; border-radius: 10px; padding: 14px 18px; margin-bottom: 28px; }
        .badge-icon { font-size: 1.2rem; color: #16a34a; }
        .badge-text { font-size: 0.9rem; color: #065f46; font-weight: 600; }

        /* CTA Button */
        .btn-wrap { text-align: center; margin-bottom: 28px; }
        .btn { display: inline-block; background: #0f1117; color: #fff; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-size: 0.95rem; font-weight: 700; letter-spacing: 0.01em; }

        /* Divider */
        .divider { border: none; border-top: 1px solid #f3f4f6; margin-bottom: 20px; }

        .footnote { font-size: 0.82rem; color: #9ca3af; line-height: 1.6; text-align: center; }

        /* Footer */
        .footer { background: #f9fafb; padding: 20px 40px; text-align: center; border-top: 1px solid #f3f4f6; }
        .footer p { font-size: 0.8rem; color: #9ca3af; }
        .footer a { color: #6ee7b7; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <span class="header-icon">⬡</span>
            <h1>NTI Portal</h1>
        </div>

        <div class="body">
            <p class="greeting">Hello {{ $user->first_name }}! 👋</p>
            <p class="text">
                Great news — your account has been reviewed and approved by an NTI administrator.
                You can now sign in and access all portal features.
            </p>

            <div class="badge-row">
                <span class="badge-icon">✓</span>
                <span class="badge-text">Your account is now active</span>
            </div>

            <div class="btn-wrap">
                <a href="{{ env('FRONTEND_URL') }}/login" class="btn">Sign In to Portal</a>
            </div>

            <hr class="divider">

            <p class="footnote">
                If you have any questions, contact your NTI administrator.<br>
                You're receiving this email because you registered on the NTI Portal.
            </p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} <a href="{{ env('FRONTEND_URL') }}">NTI Portal</a> — Nitriansky Technologický Inkubátor</p>
        </div>
    </div>
</div>
</body>
</html>
