<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Catalog\Models\Category;

class CategoryStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Category $category,
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
        $title = $isApproved ? 'Category Proposal Approved' : 'Category Proposal Rejected';
        $message = $isApproved
            ? "Your proposed category \"{$this->category->name}\" has been approved."
            : "Your proposed category \"{$this->category->name}\" was rejected." . ($this->reason ? " Reason: {$this->reason}" : '');

        return [
            'type'          => 'catalog',
            'title'         => $title,
            'message'       => $message,
            'action_url'    => '/categories',
            'status'        => $this->status,
            'reason'        => $this->reason,
            'category_id'   => $this->category->id,
            'category_uuid' => $this->category->uuid,
            'category_name' => $this->category->name,
        ];
    }
}
