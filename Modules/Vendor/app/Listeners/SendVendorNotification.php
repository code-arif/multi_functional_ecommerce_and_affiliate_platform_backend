<?php

namespace Modules\Vendor\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Notifications\Models\Notification;
use Modules\Vendor\Emails\NewVendorAdminAlertMail;
use Modules\Vendor\Emails\VendorWelcomeMail;
use Modules\Vendor\Events\VendorApprovedEvent;
use Modules\Vendor\Events\VendorRegisteredEvent;
use Modules\Vendor\Events\VendorRejectedEvent;
use Modules\Vendor\Jobs\SendVendorWelcomeEmail;
use Throwable;

class SendVendorNotification
{
    /**
     * Handle vendor lifecycle events.
     */
    public function handle(VendorRegisteredEvent|VendorApprovedEvent|VendorRejectedEvent $event): void
    {
        $vendor = $event->vendor;
        $user = $vendor->user ?? User::find($vendor->user_id);

        match ($event::class) {
            VendorRegisteredEvent::class => $this->handleRegistered($vendor, $user),
            VendorApprovedEvent::class => $this->handleApproved($vendor, $user),
            VendorRejectedEvent::class => $this->handleRejected($vendor, $user, $event->reason ?? ''),
            default                 => null,
        };
    }

    private function handleRegistered($vendor, $user): void
    {
        Log::info("Vendor registered: {$vendor->shop_name} (User: " . ($user?->id ?? 'unknown') . ")");

        // 1. Create in-app notification for admin
        try {
            $adminUsers = User::role(['super-admin', 'admin'])->get();
            if ($adminUsers->isEmpty()) {
                $fallback = User::find(1);
                if ($fallback) {
                    $adminUsers = collect([$fallback]);
                }
            }
            foreach ($adminUsers as $admin) {
                Notification::create([
                    'type' => 'vendor_registered',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $admin->id,
                    'data' => [
                        'title' => 'New Vendor Registration',
                        'message' => "New vendor registration: {$vendor->shop_name}",
                        'vendor_id' => $vendor->id,
                        'action_url' => "/vendors/{$vendor->id}",
                        'type' => 'vendor',
                    ],
                ]);
            }
        } catch (Throwable $e) {
            Log::warning("Could not create admin notification: " . $e->getMessage());
        }

        // 2. Queue acknowledgement email to the vendor
        try {
            $vendorEmail = $vendor->email ?: $user?->email;
            if ($vendorEmail) {
                Mail::to($vendorEmail)->queue(new VendorWelcomeMail($vendor));
            }
        } catch (Throwable $e) {
            Log::error("Failed to queue vendor welcome email: " . $e->getMessage());
        }

        // 3. Queue alert email to the admin (delayed by 3 seconds to avoid SMTP rate-limiting)
        try {
            $adminEmail = User::role('super-admin')->value('email')
                ?? config('ecommerce.admin_email', 'admin@gmail.com');

            if ($adminEmail) {
                Mail::to($adminEmail)->later(now()->addSeconds(3), new NewVendorAdminAlertMail($vendor));
            }
        } catch (Throwable $e) {
            Log::error("Failed to queue admin vendor alert email: " . $e->getMessage());
        }
    }

    private function handleApproved($vendor, $user): void
    {
        Log::info("Vendor approved: {$vendor->shop_name} (User: " . ($user?->id ?? 'unknown') . ")");

        // Create in-app notification for vendor
        if ($user) {
            Notification::create([
                'type' => 'vendor_approved',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => [
                    'title' => 'Shop Application Approved',
                    'message' => "Congratulations! Your vendor shop '{$vendor->shop_name}' has been approved. You can now start selling.",
                    'vendor_id' => $vendor->id,
                    'action_url' => "/vendor/dashboard",
                    'type' => 'vendor',
                ],
            ]);
        }

        // Dispatch queued job for welcome email
        SendVendorWelcomeEmail::dispatch($vendor);
    }

    private function handleRejected($vendor, $user, string $reason): void
    {
        Log::info("Vendor rejected: {$vendor->shop_name} (Reason: {$reason})");

        // Create in-app notification for vendor
        if ($user) {
            Notification::create([
                'type' => 'vendor_rejected',
                'notifiable_type' => User::class,
                'notifiable_id'   => $user->id,
                'data' => [
                    'title' => 'Shop Application Rejected',
                    'message' => "Your vendor application for '{$vendor->shop_name}' was rejected. Reason: {$reason}",
                    'vendor_id' => $vendor->id,
                    'reason' => $reason,
                    'action_url' => "/vendor/dashboard",
                    'type' => 'vendor',
                ],
            ]);
        }
    }
}
