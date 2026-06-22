<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email – Rapture</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #070b14;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #070b14; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background-color: #0d1220; border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; overflow: hidden;">

                    <!-- Top neon bar -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #ff2d9b, #c0157a, #ff2d9b); padding: 0;"></td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 48px 40px 32px;">
                            <!-- Logo circle -->
                            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(255,45,155,0.12); border: 1px solid rgba(255,255,255,0.15); display: inline-block; line-height: 64px; text-align: center; margin-bottom: 20px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #ff2d9b, #c0157a); display: inline-block; vertical-align: middle;"></div>
                            </div>

                            <p style="margin: 0 0 4px; font-family: Georgia, serif; font-size: 13px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; color: #ff2d9b;">Welcome to</p>
                            <h1 style="margin: 0 0 8px; font-family: Georgia, serif; font-size: 36px; font-weight: 700; color: #ffffff; letter-spacing: 1px;">Rapture</h1>
                            <p style="margin: 0; font-size: 14px; color: rgba(255,255,255,0.4);">Quezon City's most fabulous venue</p>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <div style="height: 1px; background: linear-gradient(90deg, transparent, rgba(255,45,155,0.4), transparent);"></div>
                        </td>
                    </tr>

                    <!-- Body content -->
                    <tr>
                        <td style="padding: 36px 40px;">
                            <p style="margin: 0 0 8px; font-size: 18px; font-weight: 600; color: #ffffff;">Hey {{ $user->first_name }} 👋</p>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: rgba(255,255,255,0.55);">
                                You're one step away from joining the most fabulous community in the metro. Verify your email to unlock your Rapture account and start living your best night life.
                            </p>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 8px 0 32px;">
                                        <a href="{{ $verificationUrl }}"
                                           style="display: inline-block; background: linear-gradient(135deg, #ff2d9b, #c0157a); color: #ffffff; padding: 16px 48px; text-decoration: none; border-radius: 999px; font-weight: 700; font-size: 15px; letter-spacing: 0.5px;">
                                            ✦ Verify My Email
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- URL fallback box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: rgba(255,255,255,0.3);">Or copy this link</p>
                                        <a href="{{ $verificationUrl }}" style="color: #ff2d9b; font-size: 12px; word-break: break-all; text-decoration: none;">{{ $verificationUrl }}</a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Warning box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: rgba(255,45,155,0.06); border: 1px solid rgba(255,45,155,0.2); border-radius: 12px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #ff2d9b;">⚠️ This link expires in 24 hours.</p>
                                        <p style="margin: 0; font-size: 13px; color: rgba(255,255,255,0.4); line-height: 1.6;">If you didn't sign up for Rapture, you can safely ignore this email. Your address won't be added without verification.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- What's waiting -->
                    <tr>
                        <td style="padding: 0 40px 36px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <p style="margin: 0 0 16px; font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.3);">What's waiting for you</p>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 8px 0; width: 28px; font-size: 16px;">🎭</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: rgba(255,255,255,0.6);">Drag shows, DJ nights & live performances</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 16px;">🍸</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: rgba(255,255,255,0.6);">Signature cocktails & bottomless brunch</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 16px;">🌈</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: rgba(255,255,255,0.6);">Pride events & safe community space</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 16px;">💎</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: rgba(255,255,255,0.6);">VIP booth reservations & exclusive access</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Bottom neon bar -->
                    <tr>
                        <td style="height: 1px; background: linear-gradient(90deg, transparent, rgba(255,45,155,0.3), transparent); padding: 0;"></td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 28px 40px;">
                            <p style="margin: 0 0 4px; font-size: 12px; color: rgba(255,255,255,0.2);">© {{ date('Y') }} Rapture Bar & Lounge · Tomas Morato Ave., Quezon City</p>
                            <p style="margin: 0; font-size: 12px; color: rgba(255,255,255,0.2);">This is an automated message — please do not reply.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>