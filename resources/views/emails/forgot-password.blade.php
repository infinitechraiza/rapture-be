<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 50%, #000000 100%); border-radius: 15px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding-bottom: 30px;">
                            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 5px 15px rgba(251, 191, 36, 0.4);">
                            <img src="{{ public_path('/images/vencios.jpg') }}" alt="Watermark"
                                style="width: 100%; height: 100%; object-fit: contain;">
                            </div>
                        </td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding-bottom: 30px;">
                            <h1 style="color: #fbbf24; margin: 0 0 10px 0; font-size: 32px; font-family: serif;">🔐 Reset Your Password</h1>
                            <p style="color: #fde68a; margin: 0; font-size: 16px;">Secure your account with a new password</p>
                        </td>
                    </tr>

                    <!-- Content Box -->
                    <tr>
                        <td>
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: rgba(0, 0, 0, 0.3); padding: 30px; border-radius: 10px; backdrop-filter: blur(10px); border: 1px solid rgba(251, 191, 36, 0.2);">
                                <tr>
                                    <td>
                                        <p style="color: #fbbf24; font-size: 18px; font-weight: 600; margin: 0 0 15px 0;">Hello {{ $user->first_name }},</p>
                                        
                                        <p style="color: #fde68a; font-size: 16px; margin: 0 0 20px 0;">We received a request to reset the password for your <strong style="color: #fbbf24;">Rapture Cafe Bar</strong> account.</p>
                                        
                                        <p style="color: #fde68a; font-size: 16px; margin: 0 0 20px 0;">Click the button below to create a new password:</p>

                                        <!-- Button -->
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="padding: 25px 0;">
                                                    <a href="{{ $resetUrl }}" style="display: inline-block; background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: #fde68a; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);">🔑 Reset My Password</a>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Divider -->
                                        <div style="height: 1px; background: linear-gradient(90deg, transparent, #fbbf24, transparent); margin: 25px 0;"></div>

                                        <!-- URL Box -->
                                        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: rgba(0, 0, 0, 0.5); padding: 15px; border-radius: 8px; border-left: 4px solid #fbbf24; margin: 20px 0;">
                                            <tr>
                                                <td>
                                                    <p style="color: #fde68a; font-size: 14px; margin: 0 0 10px 0; font-weight: 600;">If the button doesn't work, copy and paste this link:</p>
                                                    <a href="{{ $resetUrl }}" style="color: #fbbf24; word-break: break-all; font-size: 13px;">{{ $resetUrl }}</a>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Warning Box -->
                                        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: rgba(251, 191, 36, 0.1); border-left: 4px solid #fbbf24; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                            <tr>
                                                <td>
                                                    <p style="color: #fbbf24; margin: 0 0 5px 0; font-weight: bold;">⚠️ Important:</p>
                                                    <p style="color: #fde68a; margin: 0; font-size: 14px;">This password reset link will expire in <strong>1 hour</strong>.</p>
                                                    <p style="color: #fde68a; margin: 5px 0 0 0; font-size: 14px;">If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Security Notice -->
                                        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: rgba(220, 38, 38, 0.1); border-left: 4px solid #dc2626; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                            <tr>
                                                <td>
                                                    <p style="color: #fbbf24; margin: 0 0 5px 0; font-weight: bold;">🛡️ Security Tip:</p>
                                                    <p style="color: #fde68a; margin: 0; font-size: 14px;">Never share your password with anyone. Rapture Cafe Bar will never ask for your password via email.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding-top: 30px; border-top: 1px solid rgba(251, 191, 36, 0.2);">
                            <p style="color: rgba(253, 230, 138, 0.6); font-size: 12px; margin: 5px 0;">If you're having trouble clicking the button, copy and paste the URL into your web browser.</p>
                            <p style="color: rgba(253, 230, 138, 0.6); font-size: 12px; margin: 5px 0;">© {{ date('Y') }} Rapture Cafe Bar. All rights reserved.</p>
                            <p style="color: rgba(253, 230, 138, 0.6); font-size: 12px; margin: 5px 0;">This is an automated message, please do not reply.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>