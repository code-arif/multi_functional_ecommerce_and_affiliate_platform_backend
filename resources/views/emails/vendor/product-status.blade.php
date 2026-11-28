@extends('emails.layouts.master')

@php
    $badgeText = match ($status) {
        'active'   => 'Product Activated',
        'inactive' => 'Product Inactive',
        'draft'    => 'Moved to Draft',
        'pending'  => 'Pending Review',
        default    => 'Status Updated',
    };

    $heading = match ($status) {
        'active'   => 'Your Product Is Now Active',
        'inactive' => 'Your Product Status Changed to Inactive',
        'draft'    => 'Your Product Has Been Moved to Draft',
        'pending'  => 'Your Product Is Pending Review',
        default    => 'Product Status Updated',
    };

    $summaryMessage = match ($status) {
        'active'   => 'Good news. Your product has been reviewed and activated on ' . config('ecommerce.store_name', 'Z-SHOP') . '. Customers can now browse and purchase it on the storefront.',
        'inactive' => 'An administrator changed your product status to Inactive. It is currently hidden from storefront listings until re-activated.',
        'draft'    => 'An administrator moved your product to Draft status. Please review its details in your Seller Portal and update if needed.',
        'pending'  => 'Your product status has been set to Pending Review by an administrator.',
        default    => 'Your product status has been updated by an administrator.',
    };

    $statusColor = match ($status) {
        'active'   => '#15803d',
        'inactive' => '#dc2626',
        'draft'    => '#475569',
        default    => '#b45309',
    };
@endphp

@section('title', $heading . ' - ' . $product->name)
@section('badge', $badgeText)

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="padding-bottom: 8px;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                {{ $heading }}
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 24px;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #475569;">
                Hello {{ $vendor->user?->name ?? $vendor->shop_name }},<br />
                {{ $summaryMessage }}
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding-bottom: 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background-color: #ffffff;">
                <tr style="background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 16px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #334155; letter-spacing: 0.5px;">
                        Product Status Summary
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" width="40%" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Product Name
                    </td>
                    <td class="data-table-value" width="60%" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">
                        {{ $product->name }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        SKU
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-family: monospace; color: #2563eb; border-bottom: 1px solid #f1f5f9;">
                        {{ $product->sku ?: 'Not specified' }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Product Type
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ ucfirst($product->type ?? 'simple') }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        New Status
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: {{ $statusColor }}; border-bottom: 1px solid #f1f5f9;">
                        {{ ucfirst($status) }}
                    </td>
                </tr>
                @if(!empty($reason))
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Admin Note
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $reason }}
                    </td>
                </tr>
                @endif
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #fafafa;">
                        Updated At
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b;">
                        {{ now()->format('M d, Y - h:i A') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td align="center" style="padding-bottom: 24px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="background-color: #0f172a; border-radius: 4px;">
                        <a href="{{ config('ecommerce.vendor_url', 'http://localhost:3003') }}/products/{{ $product->uuid }}" target="_blank" style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px;">
                            View Product in Seller Portal
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
