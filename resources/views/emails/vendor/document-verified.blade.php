@extends('emails.layouts.master')

@section('title', 'KYC Document Verified - ' . ($vendor?->shop_name ?? 'Seller Account'))
@section('badge', 'KYC Verification')

@section('content')
@php
    $docLabel = strtoupper(str_replace('_', ' ', $document->type));
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="padding-bottom: 8px;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                KYC Document Verified
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 24px;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #475569;">
                Hello {{ $vendor?->user?->name ?? $vendor?->shop_name ?? 'Seller' }},<br />
                Your submitted verification document for <strong>{{ $vendor?->shop_name ?? 'your store' }}</strong> has been reviewed and marked as <strong>Verified</strong> by our compliance team.
            </p>
        </td>
    </tr>

    <!-- Document Summary Table -->
    <tr>
        <td style="padding-bottom: 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background-color: #ffffff;">
                <tr style="background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 16px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #334155; letter-spacing: 0.5px;">
                        Document Verification Details
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" width="40%" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Document Type
                    </td>
                    <td class="data-table-value" width="60%" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">
                        {{ $docLabel }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Document Number
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $document->document_number ?? 'Not provided' }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Verification Status
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: #15803d; border-bottom: 1px solid #f1f5f9;">
                        Verified
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #fafafa;">
                        Verified At
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b;">
                        {{ $document->verified_at ? $document->verified_at->format('M d, Y - h:i A') : now()->format('M d, Y - h:i A') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #64748b;">
                No further action is required for this document. Contact <a href="mailto:{{ config('ecommerce.store_email', 'info@zshop.com') }}" style="color: #2563eb; text-decoration: none;">{{ config('ecommerce.store_email', 'info@zshop.com') }}</a> if you need assistance.
            </p>
        </td>
    </tr>
</table>
@endsection
