@extends('emails.layouts.master')

@section('title', 'Seller Account Suspended - ' . $vendor->shop_name)
@section('badge', 'Compliance Notice')

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="padding-bottom: 8px;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                Notice of Seller Account Suspension
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 24px;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #475569;">
                Hello {{ $vendor->user?->name ?? $vendor->shop_name }},<br />
                This is to inform you that your seller storefront <strong>{{ $vendor->shop_name }}</strong> on {{ config('ecommerce.store_name', 'Z-SHOP') }} has been temporarily suspended. While suspended, your products are hidden from the marketplace and Seller Portal operations are restricted.
            </p>
        </td>
    </tr>

    <!-- Suspension Summary Table -->
    <tr>
        <td style="padding-bottom: 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background-color: #ffffff;">
                <tr style="background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 16px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #334155; letter-spacing: 0.5px;">
                        Account Status Details
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" width="40%" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Shop Name
                    </td>
                    <td class="data-table-value" width="60%" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">
                        {{ $vendor->shop_name }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Account Status
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: #c2410c; border-bottom: 1px solid #f1f5f9;">
                        Suspended
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #fafafa;">
                        Suspension Reason
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; line-height: 1.5;">
                        {{ $reason ?: 'Policy compliance review required by marketplace administration.' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding-bottom: 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 20px;">
                <tr>
                    <td style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #0f172a; padding-bottom: 10px; letter-spacing: 0.5px;">
                        Resolution & Appeal
                    </td>
                </tr>
                <tr>
                    <td style="font-size: 13px; line-height: 1.6; color: #475569;">
                        Please contact our Seller Compliance Team to resolve any outstanding compliance issues or submit an appeal for account reinstatement.
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #64748b;">
                Compliance Support: <a href="mailto:{{ config('ecommerce.store_email', 'info@zshop.com') }}" style="color: #2563eb; text-decoration: none;">{{ config('ecommerce.store_email', 'info@zshop.com') }}</a>
            </p>
        </td>
    </tr>
</table>
@endsection
