<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', config('ecommerce.store_name', 'ZShop'))</title>
    <style type="text/css">
        /* Client-specific Resets */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; height: 100% !important; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }

        /* Responsive Styles */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .content-padding { padding: 20px !important; }
            .mobile-stack { display: block !important; width: 100% !important; box-sizing: border-box !important; }
            .data-table-label { width: 100% !important; display: block !important; padding-bottom: 2px !important; }
            .data-table-value { width: 100% !important; display: block !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc;">
    <!-- Outer Background Wrapper -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; width: 100%;">
        <tr>
            <td align="center" style="padding: 30px 15px;">
                <!-- Main Email Card Container (Max-width 600px) -->
                <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden;">
                    <!-- Brand Header -->
                    <tr>
                        <td align="left" style="background-color: #0f172a; padding: 22px 32px; border-bottom: 2px solid #e2e8f0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        <a href="{{ config('ecommerce.frontend_url', '#') }}" target="_blank" style="text-decoration: none; color: #ffffff; font-size: 19px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;">
                                            {{ config('ecommerce.store_name', 'Z-SHOP') }}
                                        </a>
                                    </td>
                                    <td align="right" style="color: #94a3b8; font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">
                                        @yield('badge', 'Notification')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Email Body Content -->
                    <tr>
                        <td class="content-padding" style="padding: 32px; background-color: #ffffff;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Professional Corporate Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 24px 32px; border-top: 1px solid #e2e8f0; text-align: center;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="color: #64748b; font-size: 12px; line-height: 1.6;">
                                        <p style="margin: 0 0 6px 0; font-weight: 600; color: #475569;">
                                            {{ config('ecommerce.store_name', 'Z-SHOP') }} Marketplace Platform
                                        </p>
                                        <p style="margin: 0 0 6px 0;">
                                            This is an automated system email. Please do not reply directly to this message.
                                        </p>
                                        <p style="margin: 0; color: #94a3b8; font-size: 11px;">
                                            &copy; {{ date('Y') }} {{ config('ecommerce.store_name', 'Z-SHOP') }}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <!-- End Main Email Card Container -->
            </td>
        </tr>
    </table>
</body>
</html>
