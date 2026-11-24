@extends('emails.layouts.master')

@section('title', 'Vendor Application Received - ' . $vendor->shop_name)
@section('badge', 'Seller Onboarding')

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <!-- Greeting & Title -->
    <tr>
        <td style="padding-bottom: 8px;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                Vendor Application Received
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 24px;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #475569;">
                Hello {{ $vendor->user?->name ?? $vendor->shop_name }},<br />
                Thank you for applying to sell on {{ config('ecommerce.store_name', 'Z-SHOP') }}. Your seller account for <strong>{{ $vendor->shop_name }}</strong> has been created and received by our compliance team.
            </p>
        </td>
    </tr>

    <!-- Structured Key-Value Table -->
    <tr>
        <td style="padding-bottom: 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background-color: #ffffff;">
                <tr style="background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 16px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #334155; letter-spacing: 0.5px;">
                        Submitted Shop Details
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
                        Registered Email
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $vendor->email ?? $vendor->user?->email }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Contact Phone
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $vendor->phone ?? $vendor->user?->phone ?? 'Not provided' }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #fafafa;">
                        Account Status
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: #b45309;">
                        Pending Document Verification
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Next Steps Section -->
    <tr>
        <td style="padding-bottom: 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 20px;">
                <tr>
                    <td style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: #0f172a; padding-bottom: 12px; letter-spacing: 0.5px;">
                        What Happens Next
                    </td>
                </tr>
                <tr>
                    <td style="font-size: 13px; line-height: 1.6; color: #475569;">
                        <p style="margin: 0 0 10px 0;">
                            <strong>1. Document Review:</strong> Our merchant compliance team will inspect your Trade License and identity credentials within 24 business hours.
                        </p>
                        <p style="margin: 0 0 10px 0;">
                            <strong>2. Verification Check:</strong> An onboarding specialist may reach out by phone to confirm your warehouse pickup details.
                        </p>
                        <p style="margin: 0;">
                            <strong>3. Account Activation:</strong> Once approved, you will receive an activation email with access to the Seller Center to list products and start selling.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Action Link -->
    <tr>
        <td align="center" style="padding-bottom: 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="background-color: #0f172a; border-radius: 4px;">
                        <a href="{{ config('ecommerce.frontend_url', 'http://localhost:3000') }}" target="_blank" style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px;">
                            Browse Marketplace
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Help & Support -->
    <tr>
        <td style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #64748b;">
                If you have questions about your application or need to update submitted details, please contact merchant support at <a href="mailto:{{ config('ecommerce.store_email', 'info@zshop.com') }}" style="color: #2563eb; text-decoration: none;">{{ config('ecommerce.store_email', 'info@zshop.com') }}</a>.
            </p>
        </td>
    </tr>
</table>
@endsection
