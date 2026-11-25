<?php

namespace Modules\Notifications\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the notification into an array.
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->data) ? $this->data : (json_decode($this->data, true) ?: []);

        $rawType = $this->type ?? 'system';
        $normalizedType = $this->resolveType($rawType, $payload);
        $title = $payload['title'] ?? $this->resolveTitle($normalizedType, $payload);
        $message = $payload['message'] ?? $payload['body'] ?? 'You have a new notification.';
        $actionUrl = $payload['action_url'] ?? $payload['url'] ?? null;

        return [
            'id'          => (string) $this->id,
            'type'        => $normalizedType,
            'raw_type'    => $rawType,
            'title'       => $title,
            'message'     => $message,
            'action_url'  => $actionUrl,
            'data'        => $payload,
            'is_read'     => $this->read_at !== null,
            'read_at'     => $this->read_at ? ($this->read_at instanceof \DateTimeInterface ? $this->read_at->toIso8601String() : (string) $this->read_at) : null,
            'created_at'  => $this->created_at ? ($this->created_at instanceof \DateTimeInterface ? $this->created_at->toIso8601String() : (string) $this->created_at) : now()->toIso8601String(),
        ];
    }

    /**
     * Normalize notification type for frontend category badges.
     */
    private function resolveType(string $rawType, array $payload): string
    {
        if (isset($payload['type']) && is_string($payload['type'])) {
            return strtolower($payload['type']);
        }

        $lower = strtolower($rawType);
        if (str_contains($lower, 'order')) return 'order';
        if (str_contains($lower, 'vendor')) return 'vendor';
        if (str_contains($lower, 'stock') || str_contains($lower, 'inventory')) return 'inventory';
        if (str_contains($lower, 'review')) return 'review';
        if (str_contains($lower, 'affiliate') || str_contains($lower, 'payout')) return 'affiliate';
        if (str_contains($lower, 'user') || str_contains($lower, 'customer')) return 'user';

        return 'system';
    }

    /**
     * Resolve user-friendly notification title.
     */
    private function resolveTitle(string $type, array $payload): string
    {
        return match ($type) {
            'order'     => 'Order Update',
            'vendor'    => 'Vendor Application',
            'inventory' => 'Inventory Alert',
            'user'      => 'Account Notification',
            'review'    => 'New Product Review',
            'affiliate' => 'Affiliate Notification',
            default     => 'System Alert',
        };
    }
}
