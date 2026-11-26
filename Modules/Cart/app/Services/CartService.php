<?php

namespace Modules\Cart\Services;

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Events\CartUpdated;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Promotions\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Str;

class CartService
{
    public function getCart(?User $user, ?string $sessionId): Cart
    {
        if ($user) {
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => Str::random(40)]
            );
        } elseif ($sessionId) {
            $cart = Cart::firstOrCreate(
                ['session_id' => $sessionId],
                ['session_id' => $sessionId]
            );
        } else {
            $sessionId = Str::random(40);
            $cart = Cart::create(['session_id' => $sessionId]);
        }

        $cart->load('items.product', 'items.variant');
        return $cart;
    }

    /**
     * Add an item to the cart with server-side pricing and stock validation (supports simple + variable products).
     */
    public function addItem(Cart $cart, array $data): CartItem
    {
        $product = Product::findByUuidOrFail($data['product_uuid']);
        if ($product->status !== 'active') {
            abort(400, 'This product is not available for purchase.');
        }

        $variant = !empty($data['variant_uuid']) ? ProductVariant::findByUuidOrFail($data['variant_uuid']) : null;
        if ($variant && ($variant->product_id !== $product->id || !$variant->is_active)) {
            abort(400, 'Invalid product variant selected.');
        }

        $requestedQty = (int) ($data['quantity'] ?? 1);
        $maxQty = config('ecommerce.cart.max_quantity', 100);

        if ($requestedQty > $maxQty) {
            abort(400, "Maximum quantity per item is {$maxQty}.");
        }

        $existing = $cart->items()
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->first();

        $currentQty = $existing ? $existing->quantity : 0;
        $newQty = $currentQty + $requestedQty;

        if ($newQty > $maxQty) {
            abort(400, "Maximum quantity per item is {$maxQty}.");
        }

        // Stock validation: variable products check variant stock, simple products check product stock
        if ($variant) {
            if ($newQty > $variant->stock_quantity) {
                abort(400, "Only {$variant->stock_quantity} units available for this variant.");
            }
        } elseif ($product->manage_stock) {
            if ($newQty > $product->stock_quantity) {
                abort(400, "Only {$product->stock_quantity} units available.");
            }
        }

        // Always compute unit price server-side — never trust client-supplied prices
        $unitPrice = $variant
            ? ($variant->sale_price ?? $variant->price ?? $product->current_price)
            : $product->current_price;

        if ($existing) {
            $existing->update([
                'quantity'    => $newQty,
                'unit_price'  => $unitPrice,
                'total_price' => round($unitPrice * $newQty, 2),
            ]);
            CartUpdated::dispatch($cart);
            return $existing->fresh();
        }

        $item = $cart->items()->create([
            'product_id'  => $product->id,
            'variant_id'  => $variant?->id,
            'quantity'    => $requestedQty,
            'unit_price'  => $unitPrice,
            'total_price' => round($unitPrice * $requestedQty, 2),
        ]);

        CartUpdated::dispatch($cart);

        return $item;
    }

    public function updateItem(CartItem $item, array $data): CartItem
    {
        $quantity = $data['quantity'] ?? 1;

        if ($quantity <= 0) {
            $item->delete();
            CartUpdated::dispatch($item->cart);
            return $item;
        }

        $maxQty = config('ecommerce.cart.max_quantity', 100);
        if ($quantity > $maxQty) {
            abort(400, "Maximum quantity per item is {$maxQty}.");
        }

        // Stock validation: check variant stock or product stock
        if ($item->variant_id) {
            $variant = $item->variant;
            if ($variant && $quantity > $variant->stock_quantity) {
                abort(400, "Only {$variant->stock_quantity} units available for this variant.");
            }
        } elseif ($item->product && $item->product->manage_stock) {
            if ($quantity > $item->product->stock_quantity) {
                abort(400, "Only {$item->product->stock_quantity} units available.");
            }
        }

        $item->update([
            'quantity'    => $quantity,
            'total_price' => $item->unit_price * $quantity,
        ]);

        CartUpdated::dispatch($item->cart);

        return $item->fresh();
    }

    public function removeItem(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        CartUpdated::dispatch($cart);
    }

    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        CartUpdated::dispatch($cart);
    }

    public function applyCoupon(Cart $cart, string $couponCode): Cart
    {
        $coupon = Coupon::where('code', $couponCode)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->first();

        if (!$coupon) {
            abort(422, 'Invalid or expired coupon code.');
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            abort(422, 'This coupon has reached its usage limit.');
        }

        $subtotal = (float) $cart->subtotal;
        if ($coupon->minimum_order_amount && $subtotal < (float) $coupon->minimum_order_amount) {
            abort(422, "Minimum order amount of {$coupon->minimum_order_amount} is required for this coupon.");
        }

        $discount = $coupon->type === 'percentage'
            ? min($subtotal * ((float) $coupon->value / 100), $coupon->maximum_discount !== null ? (float) $coupon->maximum_discount : $subtotal)
            : min((float) $coupon->value, $subtotal);

        $cart->update([
            'coupon_id'       => $coupon->id,
            'coupon_code'     => $coupon->code,
            'discount_amount' => round($discount, 2),
        ]);

        CartUpdated::dispatch($cart);

        return $cart->fresh()->load('items.product', 'items.variant');
    }

    public function removeCoupon(Cart $cart): Cart
    {
        $cart->update([
            'coupon_code'     => null,
            'discount_amount' => 0,
        ]);

        CartUpdated::dispatch($cart);

        return $cart->fresh()->load('items.product', 'items.variant');
    }

    /**
     * Get cart items grouped by vendor for split checkout.
     */
    public function getItemsGroupedByVendor(Cart $cart): array
    {
        $cart->load('items.product.vendors');

        $groups = [];
        foreach ($cart->items as $item) {
            $vendorId = $item->product->vendors->first()?->id ?? 0;
            $vendorName = $item->product->vendors->first()?->shop_name ?? 'General';

            if (!isset($groups[$vendorId])) {
                $groups[$vendorId] = [
                    'vendor_id'   => $vendorId,
                    'vendor_name' => $vendorName,
                    'items'       => [],
                    'subtotal'    => 0,
                ];
            }

            $groups[$vendorId]['items'][] = $item;
            $groups[$vendorId]['subtotal'] += $item->total;
        }

        return array_values($groups);
    }

    /**
     * Merge guest cart into user cart on login.
     */
    public function mergeGuestCart(string $sessionId, User $user): Cart
    {
        $guestCart = Cart::where('session_id', $sessionId)->whereNull('user_id')->first();
        $userCart = Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['session_id' => Str::random(40)]
        );

        if (!$guestCart || $guestCart->id === $userCart->id) {
            return $userCart->load('items.product', 'items.variant');
        }

        foreach ($guestCart->items as $guestItem) {
            $existing = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('variant_id', $guestItem->variant_id)
                ->first();

            if ($existing) {
                $maxQty = config('ecommerce.cart.max_quantity', 100);
                $newQty = min($maxQty, $existing->quantity + $guestItem->quantity);
                $existing->update([
                    'quantity'    => $newQty,
                    'total_price' => round((float) $existing->unit_price * $newQty, 2),
                ]);
            } else {
                $guestItem->update(['cart_id' => $userCart->id]);
            }
        }

        $guestCart->delete();
        CartUpdated::dispatch($userCart);

        return $userCart->load('items.product', 'items.variant');
    }
}
