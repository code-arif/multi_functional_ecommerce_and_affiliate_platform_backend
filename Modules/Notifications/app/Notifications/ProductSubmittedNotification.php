<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Product\Models\Product;

class ProductSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Product $product,
        public string $submittedBy,
        public bool $isReapproval = false
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'catalog',
            'title'         => $this->isReapproval ? 'Product Updated (Re-approval Needed)' : 'New Vendor Product Submitted',
            'message'       => $this->isReapproval
                ? "{$this->submittedBy} updated product details for {$this->product->name}. Review required."
                : "{$this->submittedBy} added a new product: {$this->product->name}.",
            'action_url'    => "/products/{$this->product->uuid}",
            'product_id'    => $this->product->id,
            'product_uuid'  => $this->product->uuid,
            'product_name'  => $this->product->name,
            'submitted_by'  => $this->submittedBy,
            'is_reapproval' => $this->isReapproval,
        ];
    }
}
