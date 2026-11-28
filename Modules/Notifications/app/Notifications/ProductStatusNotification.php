<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Product\Models\Product;

class ProductStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Product $product,
        public string $status,
        public ?string $reason = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $title = match ($this->status) {
            'active'   => 'Product Approved & Active',
            'inactive' => 'Product Set to Inactive',
            'draft'    => 'Product Moved to Draft',
            'pending'  => 'Product Pending Review',
            default    => 'Product Status Updated',
        };

        $message = match ($this->status) {
            'active'   => "Your product \"{$this->product->name}\" is now active and visible on the storefront.",
            'inactive' => "Your product \"{$this->product->name}\" has been set to inactive.",
            'draft'    => "Your product \"{$this->product->name}\" has been moved to draft.",
            'pending'  => "Your product \"{$this->product->name}\" is pending review.",
            default    => "Your product \"{$this->product->name}\" status was updated to {$this->status}.",
        };

        if ($this->reason && $this->status !== 'active') {
            $message .= " Reason: {$this->reason}";
        }

        return [
            'type'         => 'catalog',
            'title'        => $title,
            'message'      => $message,
            'action_url'   => "/products/{$this->product->uuid}",
            'status'       => $this->status,
            'reason'       => $this->reason,
            'product_id'   => $this->product->id,
            'product_uuid' => $this->product->uuid,
            'product_name' => $this->product->name,
        ];
    }
}
