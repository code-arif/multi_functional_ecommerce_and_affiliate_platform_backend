<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Catalog\Models\Brand;

class BrandStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Brand $brand,
        public string $status,
        public ?string $reason = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $isApproved = $this->status === 'approved';
        $title = $isApproved ? 'Brand Proposal Approved' : 'Brand Proposal Rejected';
        $message = $isApproved
            ? "Your proposed brand \"{$this->brand->name}\" has been approved."
            : "Your proposed brand \"{$this->brand->name}\" was rejected." . ($this->reason ? " Reason: {$this->reason}" : '');

        return [
            'type'       => 'catalog',
            'title'      => $title,
            'message'    => $message,
            'action_url' => '/brands',
            'status'     => $this->status,
            'reason'     => $this->reason,
            'brand_id'   => $this->brand->id,
            'brand_uuid' => $this->brand->uuid,
            'brand_name' => $this->brand->name,
        ];
    }
}
