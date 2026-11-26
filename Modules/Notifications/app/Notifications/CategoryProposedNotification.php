<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Catalog\Models\Category;

class CategoryProposedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Category $category,
        public string $submittedBy
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'catalog',
            'title'         => 'New Category Proposal',
            'message'       => "{$this->submittedBy} proposed a new category: {$this->category->name}.",
            'action_url'    => "/categories/{$this->category->uuid}",
            'category_id'   => $this->category->id,
            'category_uuid' => $this->category->uuid,
            'category_name' => $this->category->name,
            'submitted_by'  => $this->submittedBy,
        ];
    }
}
