@extends('emails.layouts.master')

@php
    $requiresReapproval = $requiresReapproval ?? false;
    $currency = config('ecommerce.currency_symbol', '৳');
    if ($product->type === 'variable') {
        $min = number_format((float) ($product->min_price ?? 0), 2);
        $max = number_format((float) ($product->max_price ?? 0), 2);
        $priceDisplay = $min === $max ? "{$currency}{$min}" : "{$currency}{$min} - {$currency}{$max}";
        $stockDisplay = (int) ($product->total_stock ?? 0) . ' (' . $product->variants->count() . ' variants)';
    } else {
        $priceDisplay = $currency . number_format((float) ($product->price ?? 0), 2);
        $stockDisplay = (int) ($product->stock_quantity ?? 0);
    }
@endphp

@section('title', ($requiresReapproval ? 'Vendor Updated Product (Re-approval Needed) - ' : 'Vendor Updated Product - ') . $product->name)
@section('badge', 'Product Updated')

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="padding-bottom: 8px;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                {{ $requiresReapproval ? 'Vendor Updated Product (Re-approval Needed)' : 'Vendor Updated Product Details' }}
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom: 24px;">
            <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #475569;">
                @if($requiresReapproval)
                    <strong>{{ $vendor->shop_name }}</strong> modified product content, media, or variants for <strong>{{ $product->name }}</strong> on {{ config('ecommerce.store_name', 'Z-SHOP') }}. The listing status was automatically set to <strong>Inactive</strong> until you review and re-approve the update.
                @else
                    <strong>{{ $vendor->shop_name }}</strong> updated product details or pricing for <strong>{{ $product->name }}</strong> on {{ config('ecommerce.store_name', 'Z-SHOP') }}.
                @endif
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding-bottom: 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background-color: #ffffff;">
                <tr style="background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <td colspan="2" style="padding: 12px 16px; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #334155; letter-spacing: 0.5px;">
                        Updated Product Summary
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
                        SKU / Type
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $product->sku ?: 'N/A' }} ({{ ucfirst($product->type ?? 'simple') }})
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Current Price
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #f1f5f9;">
                        {{ $priceDisplay }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Stock Quantity
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $stockDisplay }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Current Status
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; font-weight: 700; color: {{ $product->status === 'active' ? '#15803d' : '#b45309' }}; border-bottom: 1px solid #f1f5f9;">
                        {{ ucfirst($product->status ?? 'inactive') }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Update Action
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $requiresReapproval ? 'Content/Media Edited — Re-approval Required' : 'Operational Update (Pricing / Stock / Draft)' }}
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #f1f5f9; background-color: #fafafa;">
                        Vendor Shop
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                        {{ $vendor->shop_name }} ({{ $vendor->user?->name ?? $vendor->email ?? 'N/A' }})
                    </td>
                </tr>
                <tr>
                    <td class="data-table-label" style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b; background-color: #fafafa;">
                        Updated At
                    </td>
                    <td class="data-table-value" style="padding: 10px 16px; font-size: 13px; color: #1e293b;">
                        {{ $product->updated_at ? $product->updated_at->format('M d, Y - h:i A') : now()->format('M d, Y - h:i A') }}
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
                        <a href="{{ config('ecommerce.admin_url', 'http://localhost:3001') }}/products/{{ $product->uuid }}" target="_blank" style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; text-transform: uppercase; letter-spacing: 0.5px;">
                            Review Product in Admin Portal
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
