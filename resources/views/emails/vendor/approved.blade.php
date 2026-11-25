@extends('emails.layouts.master')

@section('title', 'Vendor Account Approved - ' . $vendor->shop_name)
@section('badge', 'Account Activated')

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="padding-bottom: 8px;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                Your Seller Account Has Been Approved
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 24px;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #475569;">
                Hello {{ $vendor->user?->name ?? $vendor->shop_name }},<br />
                Congratulations. Your merchant application for <strong>{{ $vendor->shop_name }}</strong> on {{ config('ecommerce.store_name', 'Z-SHOP') }} has been reviewed and approved. Your storefront is now active and ready for business.
            </p>
        </td>
    </tr>

    <!-- Account Summary Table -->
    <tr>
        <td style="padding-bottom: 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background-color: #ffffff;">
                <tr style="background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 16px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #334155; letter-spacing: 0.5px;">
                        Storefront Activation Summary
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
                        Store URL Slug
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-family: monospace; color: #2563eb; border-bottom: 1px solid #f1f5f9;">
                        vendors/{{ $vendor->slug }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Commission Rate
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $vendor->commission_rate }}% ({{ ucfirst($vendor->commission_type ?? 'percentage') }})
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Account Status
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: #15803d; border-bottom: 1px solid #f1f5f9;">
                        Active
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #fafafa;">
                        Approved At
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b;">
                        {{ $vendor->approved_at ? $vendor->approved_at->format('M d, Y - h:i A') : now()->format('M d, Y - h:i A') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Getting Started -->
    <tr>
        <td style="padding-bottom: 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 20px;">
                <tr>
                    <td style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #0f172a; padding-bottom: 12px; letter-spacing: 0.5px;">
                        Next Steps in Seller Center
                    </td>
                </tr>
                <tr>
                    <td style="font-size: 13px; line-height: 1.6; color: #475569;">
                        <p style="margin: 0 0 8px 0;"><strong>1.</strong> Sign in to the Seller Portal with your registered email address.</p>
                        <p style="margin: 0 0 8px 0;"><strong>2.</strong> Complete your shop branding, return policy, and shipping configuration.</p>
                        <p style="margin: 0;"><strong>3.</strong> Publish your product catalog to start receiving marketplace orders.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- CTA Button -->
    <tr>
        <td align="center" style="padding-bottom: 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="background-color: #0f172a; border-radius: 4px;">
                        <a href="{{ config('ecommerce.vendor_url', 'http://localhost:3003') }}/login" target="_blank" style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px;">
                            Sign In to Seller Center
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #64748b;">
                For merchant onboarding support, contact our team at <a href="mailto:{{ config('ecommerce.store_email', 'info@zshop.com') }}" style="color: #2563eb; text-decoration: none;">{{ config('ecommerce.store_email', 'info@zshop.com') }}</a>.
            </p>
        </td>
    </tr>
</table>
@endsection
