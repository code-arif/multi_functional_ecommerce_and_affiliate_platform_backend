<?php

namespace Modules\Checkout\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Auth\Models\Address;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Checkout\Events\CheckoutProcessed;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Enums\PaymentStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Promotions\Models\Coupon;
use Modules\Promotions\Models\CouponUsage;

class CheckoutService
{
    public function __construct(
        private CartService $cartService
    ) {}

    /**
     * Get a full order preview (summary) without placing the order.
     */
    public function preview(Cart $cart, User $user, array $data): array
    {
        $this->ensureCartOwnership($cart, $user);

        $itemsByVendor = $this->cartService->getItemsGroupedByVendor($cart);
        $shippingAddress = $this->resolveAddress($data, $user);
        $coupon = $this->resolveValidCoupon($cart->coupon_code, $cart->subtotal, $user);
        $couponDiscount = $coupon ? $this->computeCouponDiscount($coupon, $cart->subtotal) : 0;
        $taxRate = $this->getTaxRate($shippingAddress);

        $groups = [];
        $grandTotal = 0;

        foreach ($itemsByVendor as $vendorGroup) {
            $vendorSubtotal = collect($vendorGroup['items'])->sum(fn($item) => $item->total);
            $vendorShipping = $this->calculateShippingCost($vendorSubtotal, $vendorGroup['vendor_id']);
            $vendorTax = round($vendorSubtotal * $taxRate, 2);
            $vendorTotal = $vendorSubtotal + $vendorShipping + $vendorTax;
            $vendorDiscount = $couponDiscount > 0
                ? round($couponDiscount * ($vendorSubtotal / max($cart->subtotal, 1)), 2)
                : 0;

            $groups[] = [
                'vendor_id'      => $vendorGroup['vendor_id'],
                'vendor_name'    => $vendorGroup['vendor_name'],
                'items'          => $vendorGroup['items'],
                'subtotal'       => round($vendorSubtotal, 2),
                'shipping_cost'  => $vendorShipping,
                'tax_amount'     => $vendorTax,
                'discount'       => $vendorDiscount,
                'total'          => round($vendorTotal - $vendorDiscount, 2),
            ];

            $grandTotal += $vendorTotal - $vendorDiscount;
        }

        return [
            'items_by_vendor'  => $groups,
            'subtotal'         => round($cart->subtotal, 2),
            'shipping_total'   => round(collect($groups)->sum('shipping_cost'), 2),
            'tax_total'        => round(collect($groups)->sum('tax_amount'), 2),
            'discount_total'   => round($couponDiscount, 2),
            'grand_total'      => round($grandTotal, 2),
            'coupon_code'      => $cart->coupon_code,
            'shipping_address' => $shippingAddress,
            'tax_rate'         => $taxRate,
            'item_count'       => $cart->item_count,
        ];
    }

    /**
     * Process checkout and create order(s). Supports split-by-vendor.
     *
     * Order creation, invoices and status history are delegated to the
     * Orders module (Modules\Orders\Services\OrderService::placeOrder).
     */
    public function processCheckout(Cart $cart, User $user, array $data): array
    {
        $this->ensureCartOwnership($cart, $user);

        return DB::transaction(function () use ($cart, $user, $data) {
            // Lock cart to prevent concurrent double-checkout of the same cart
            $lockedCart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $lockedCart->load('items.product.vendors', 'items.variant');

            if ($lockedCart->items->isEmpty()) {
                abort(400, 'Your cart is empty.');
            }

            $shippingAddress = $this->resolveAddress($data, $user);
            $shippingAddrJson = is_string($shippingAddress) ? $shippingAddress : json_encode($shippingAddress);
            $billingAddrJson = isset($data['billing_address'])
                ? (is_string($data['billing_address']) ? $data['billing_address'] : json_encode($data['billing_address']))
                : $shippingAddrJson;

            // Structured shipping snapshot (orders.shipping_* columns)
            $shipping = is_array($shippingAddress) ? $shippingAddress : [];
            $shippingName  = $shipping['recipient_name'] ?? $shipping['name'] ?? $user->name;
            $shippingPhone = $shipping['phone'] ?? $user->phone;
            $shippingEmail = $shipping['email'] ?? $user->email;

            $paymentMethod = $data['payment_method'] ?? 'cod';
            $notes = $data['notes'] ?? null;

            $itemsByVendor = $this->cartService->getItemsGroupedByVendor($lockedCart);

            // Recompute cart subtotal from authoritative DB prices before discount calculation
            $authoritativeSubtotal = 0.0;
            foreach ($itemsByVendor as &$vendorGroup) {
                $groupSubtotal = 0.0;
                foreach ($vendorGroup['items'] as $item) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $variant = $item->variant_id
                        ? ProductVariant::whereKey($item->variant_id)->lockForUpdate()->firstOrFail()
                        : null;

                    if ($product->status !== 'active') {
                        abort(400, "Product '{$product->name}' is no longer available.");
                    }

                    if ($variant) {
                        if ($variant->stock_quantity < $item->quantity) {
                            abort(400, "Insufficient stock for '{$product->name}'. Only {$variant->stock_quantity} left.");
                        }
                        $unitPrice = (float) ($variant->sale_price ?? $variant->price ?? $product->current_price);
                    } else {
                        if ($product->manage_stock && $product->stock_quantity < $item->quantity) {
                            abort(400, "Insufficient stock for '{$product->name}'. Only {$product->stock_quantity} left.");
                        }
                        $unitPrice = (float) $product->current_price;
                    }

                    $item->setAttribute('locked_product', $product);
                    $item->setAttribute('locked_variant', $variant);
                    $item->setAttribute('authoritative_unit_price', $unitPrice);
                    $item->setAttribute('authoritative_total', round($unitPrice * $item->quantity, 2));
                    $groupSubtotal += round($unitPrice * $item->quantity, 2);
                }
                $vendorGroup['authoritative_subtotal'] = round($groupSubtotal, 2);
                $authoritativeSubtotal += $vendorGroup['authoritative_subtotal'];
            }
            unset($vendorGroup);

            $coupon = $this->resolveValidCoupon($lockedCart->coupon_code, $authoritativeSubtotal, $user, lock: true);
            $couponDiscount = $coupon ? $this->computeCouponDiscount($coupon, $authoritativeSubtotal) : 0.0;
            $taxRate = $this->getTaxRate($shippingAddress);

            if ($coupon && $couponDiscount > 0) {
                $coupon->increment('used_count');
            }

            /** @var OrderService $orderService */
            $orderService = app(OrderService::class);
            $groupId = Order::generateGroupId();
            $orders = [];

            foreach ($itemsByVendor as $vendorGroup) {
                $vendorSubtotal = $vendorGroup['authoritative_subtotal'];
                $vendorShipping = $this->calculateShippingCost($vendorSubtotal, $vendorGroup['vendor_id']);
                $vendorTax = round($vendorSubtotal * $taxRate, 2);
                $vendorDiscount = $couponDiscount > 0
                    ? round($couponDiscount * ($vendorSubtotal / max($authoritativeSubtotal, 1)), 2)
                    : 0;
                $vendorTotal = round($vendorSubtotal + $vendorShipping + $vendorTax - $vendorDiscount, 2);

                // Build order items + deduct stock atomically on locked rows
                $items = [];
                foreach ($vendorGroup['items'] as $item) {
                    /** @var Product $product */
                    $product = $item->getAttribute('locked_product');
                    /** @var ProductVariant|null $variant */
                    $variant = $item->getAttribute('locked_variant');
                    $unitPrice = $item->getAttribute('authoritative_unit_price');
                    $lineTotal = $item->getAttribute('authoritative_total');

                    $items[] = [
                        'product_id'         => $product->id,
                        'product_variant_id' => $variant?->id,
                        'vendor_id'          => $vendorGroup['vendor_id'] > 0 ? $vendorGroup['vendor_id'] : null,
                        'product_name'       => $product->name,
                        'product_sku'        => $product->sku,
                        'variant_attributes' => $variant?->attributes ?? null,
                        'product_image'      => $product->thumbnail,
                        'unit_price'         => $unitPrice,
                        'quantity'           => $item->quantity,
                        'subtotal'           => $lineTotal,
                    ];

                    if ($variant) {
                        $variant->decrement('stock_quantity', $item->quantity);
                    } elseif ($product->manage_stock) {
                        $product->decrementStock($item->quantity);
                    }
                }

                // Delegate order creation to the Orders module
                $order = $orderService->placeOrder([
                    'user_id'          => $user->id,
                    'vendor_id'        => $vendorGroup['vendor_id'] > 0 ? $vendorGroup['vendor_id'] : null,
                    'group_id'         => $groupId,
                    'order_number'     => Order::generateOrderNumber(),
                    'status'           => OrderStatus::Pending->value,
                    'subtotal'         => $vendorSubtotal,
                    'shipping_charge'  => $vendorShipping,
                    'discount_amount'  => $vendorDiscount,
                    'coupon_discount'  => $vendorDiscount,
                    'tax_amount'       => $vendorTax,
                    'total_amount'     => $vendorTotal,
                    'coupon_id'        => $coupon?->id,
                    'coupon_code'      => $coupon?->code,
                    'payment_method'   => $paymentMethod,
                    'payment_status'   => PaymentStatus::Pending->value,
                    'shipping_method'  => $data['shipping_method'] ?? 'standard',
                    'shipping_name'    => $shippingName,
                    'shipping_phone'   => $shippingPhone,
                    'shipping_email'   => $shippingEmail,
                    'shipping_address_line1' => $shipping['address_line'] ?? $shipping['address_line1'] ?? $shipping['full'] ?? '',
                    'shipping_address_line2' => $shipping['address_line2'] ?? null,
                    'shipping_city'    => $shipping['city'] ?? '',
                    'shipping_state'   => $shipping['state'] ?? null,
                    'shipping_postal_code' => $shipping['postal_code'] ?? null,
                    'shipping_country' => $shipping['country'] ?? 'Bangladesh',
                    'shipping_address' => $shippingAddrJson,
                    'billing_address'  => $billingAddrJson,
                    'customer_note'    => $notes,
                    'tracking_token'   => Str::random(32),
                    'items'            => $items,
                    'actor_type'       => 'customer',
                    'actor_name'       => $user->name,
                ]);

                if ($coupon && $vendorDiscount > 0) {
                    CouponUsage::create([
                        'coupon_id'       => $coupon->id,
                        'user_id'         => $user->id,
                        'order_id'        => $order->id,
                        'discount_amount' => $vendorDiscount,
                    ]);
                }

                $orders[] = $order;
            }

            $lockedCart->items()->delete();
            $lockedCart->update([
                'coupon_id'       => null,
                'coupon_code'     => null,
                'discount_amount' => 0,
            ]);

            CheckoutProcessed::dispatch($lockedCart, $orders, $user);

            Log::info('Checkout completed', [
                'user_id' => $user->id,
                'orders'  => collect($orders)->pluck('order_number')->toArray(),
                'total'   => collect($orders)->sum('total_amount'),
            ]);

            return $orders;
        });
    }

    public function getShippingOptions(Cart $cart): array
    {
        $itemsByVendor = $this->cartService->getItemsGroupedByVendor($cart);
        $options = [];

        foreach ($itemsByVendor as $vendorGroup) {
            $vendorSubtotal = collect($vendorGroup['items'])->sum(fn($item) => $item->total);
            $freeOver = config('ecommerce.shipping.free_over', 1000);

            $options[] = [
                'vendor_id'     => $vendorGroup['vendor_id'],
                'vendor_name'   => $vendorGroup['vendor_name'],
                'methods'       => [
                    [
                        'id'     => 'standard',
                        'name'   => 'Standard Shipping',
                        'cost'   => $vendorSubtotal >= $freeOver ? 0 : config('ecommerce.shipping.default_charge', 60),
                        'estimated_days' => '5-7 business days',
                    ],
                    [
                        'id'     => 'express',
                        'name'   => 'Express Shipping',
                        'cost'   => $vendorSubtotal >= $freeOver ? 5 : config('ecommerce.shipping.default_charge', 60) + 30,
                        'estimated_days' => '1-2 business days',
                    ],
                ],
                'free_shipping_over' => $freeOver,
            ];
        }

        return $options;
    }

    public function calculateTax(float $subtotal, array $addressData): float
    {
        $rate = $this->getTaxRate($addressData);
        return round($subtotal * $rate, 2);
    }

    private function ensureCartOwnership(Cart $cart, User $user): void
    {
        if ($cart->user_id && $cart->user_id !== $user->id) {
            abort(403, 'This cart does not belong to you.');
        }
    }

    private function resolveValidCoupon(?string $couponCode, float $subtotal, User $user, bool $lock = false): ?Coupon
    {
        if (!$couponCode) {
            return null;
        }

        $query = Coupon::where('code', $couponCode)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));

        if ($lock) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();
        if (!$coupon) {
            return null;
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return null;
        }

        if ($coupon->usage_limit_per_user) {
            $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->count();
            if ($userUsageCount >= $coupon->usage_limit_per_user) {
                return null;
            }
        }

        if ($coupon->minimum_order_amount && $subtotal < (float) $coupon->minimum_order_amount) {
            return null;
        }

        return $coupon;
    }

    private function computeCouponDiscount(Coupon $coupon, float $subtotal): float
    {
        if ($coupon->type === 'percentage') {
            $discount = $subtotal * ((float) $coupon->value / 100);
            if ($coupon->maximum_discount !== null) {
                $discount = min($discount, (float) $coupon->maximum_discount);
            }
            return round(min($discount, $subtotal), 2);
        }

        return round(min((float) $coupon->value, $subtotal), 2);
    }

    private function calculateShippingCost(float $subtotal, int $vendorId): float
    {
        $freeOver = config('ecommerce.shipping.free_over', 1000);
        $baseRate = config('ecommerce.shipping.default_charge', 60);
        return $subtotal >= $freeOver ? 0 : $baseRate;
    }

    private function resolveAddress(array $data, User $user): array
    {
        if (isset($data['address_uuid'])) {
            $address = Address::where('uuid', $data['address_uuid'])
                ->where('user_id', $user->id)->first();
            if ($address) {
                return [
                    'uuid'         => $address->uuid,
                    'label'        => $address->label,
                    'address_line' => $address->address_line ?? '',
                    'city'         => $address->city ?? '',
                    'state'        => $address->state ?? '',
                    'postal_code'  => $address->postal_code ?? '',
                    'country'      => $address->country ?? '',
                ];
            }
        }
        if (isset($data['shipping_address'])) {
            return is_array($data['shipping_address']) ? $data['shipping_address'] : ['full' => $data['shipping_address']];
        }
        $default = $user->defaultAddress;
        if ($default) {
            return [
                'uuid'         => $default->uuid,
                'label'        => $default->label ?? 'Default',
                'address_line' => $default->address_line ?? '',
                'city'         => $default->city ?? '',
                'state'        => $default->state ?? '',
                'postal_code'  => $default->postal_code ?? '',
                'country'      => $default->country ?? '',
            ];
        }
        return ['full' => 'No address provided'];
    }

    private function getTaxRate(array $address): float
    {
        $country = $address['country'] ?? $address['full'] ?? '';
        $rates = [
            'Bangladesh' => 0.05, 'BD' => 0.05,
            'India'      => 0.18,
            'USA'        => 0.0,  'US' => 0.0,
            'UK'         => 0.20, 'GB' => 0.20,
        ];
        return $rates[$country] ?? config('ecommerce.tax.default_rate', 0.0);
    }
}
