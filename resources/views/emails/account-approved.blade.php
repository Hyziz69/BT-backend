<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your account was approved</title>
</head>
<body style="margin: 0; padding: 0; background: #f3f4f6; font-family: Arial, Helvetica, sans-serif; color: #111827;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f3f4f6; padding: 32px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);">
                    <tr>
                        <td style="background: #16a34a; padding: 28px 32px;">
                            <div style="font-size: 13px; letter-spacing: 1.5px; text-transform: uppercase; color: #dcfce7; font-weight: 700;">
                                NTI Account
                            </div>
                            <h1 style="margin: 10px 0 0; color: #ffffff; font-size: 26px; line-height: 1.25;">
                                Your account was approved
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 18px; font-size: 16px; line-height: 1.6;">
                                Hello {{ $user->first_name }},
                            </p>

                            <p style="margin: 0 0 18px; font-size: 16px; line-height: 1.6;">
                                Good news. Your registration request has been approved. You can now sign in using the email and password you created during registration.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 14px; margin: 22px 0;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #166534;">
                                            Your account is active and ready to use.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background: #16a34a; border-radius: 12px;">
                                        <a href="{{ $loginUrl }}" style="display: inline-block; padding: 14px 24px; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px;">
                                            Log in to NTI
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 28px 0 0; font-size: 13px; line-height: 1.6; color: #6b7280;">
                                If the button does not work, copy this link into your browser:<br>
                                <a href="{{ $loginUrl }}" style="color: #2563eb; word-break: break-all;">{{ $loginUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 18px 32px; background: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 13px; color: #6b7280;">
                                This is an automatic notification from NTI.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>