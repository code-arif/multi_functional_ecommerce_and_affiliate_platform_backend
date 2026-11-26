<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Catalog\Models\Brand;

class BrandProposedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Brand $brand,
        public string $submittedBy
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'catalog',
            'title'        => 'New Brand Proposal',
            'message'      => "{$this->submittedBy} proposed a new brand: {$this->brand->name}.",
            'action_url'   => "/brands/{$this->brand->uuid}",
            'brand_id'     => $this->brand->id,
            'brand_uuid'   => $this->brand->uuid,
            'brand_name'   => $this->brand->name,
            'submitted_by' => $this->submittedBy,
        ];
    }
}
