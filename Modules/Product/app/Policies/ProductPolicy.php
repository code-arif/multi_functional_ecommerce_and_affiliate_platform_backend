<?php

namespace Modules\Product\Policies;

use App\Models\User;
use Modules\Product\Models\Product;

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * View a product. Active products are public; pending/draft only visible to owner/seller/admin.
     */
    public function view(?User $user, Product $product): bool
    {
        if ($product->status === 'active') {
            return true;
        }

        if ($user && $user->hasPermission('products.view')) {
            return true;
        }

        if ($user && $user->vendor) {
            if ($product->isOwnedByVendor($user->vendor->id)) {
                return true;
            }

            return $product->vendorProductPrices()
                ->where('vendor_id', $user->vendor->id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        if ($user->hasPermission('products.create')) {
            return true;
        }

        return (bool) ($user->vendor && $user->isVendor());
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->hasPermission('products.edit')) {
            return true;
        }

        // Only direct vendor owner can edit master product details
        return (bool) ($user->vendor && $product->isOwnedByVendor($user->vendor->id));
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->hasPermission('products.delete')) {
            return true;
        }

        return (bool) ($user->vendor && $product->isOwnedByVendor($user->vendor->id));
    }

    /**
     * Admin can approve pending products.
     */
    public function approve(User $user): bool
    {
        return $user->hasPermission('products.approve') || $user->isAdmin();
    }
}
