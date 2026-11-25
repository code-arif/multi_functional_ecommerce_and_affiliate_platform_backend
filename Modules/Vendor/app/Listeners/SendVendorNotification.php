<?php

namespace Modules\Vendor\Listeners;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Notifications\Models\Notification;
use Modules\Vendor\Emails\NewVendorAdminAlertMail;
use Modules\Vendor\Emails\VendorApprovedMail;
use Modules\Vendor\Emails\VendorDocumentRejectedMail;
use Modules\Vendor\Emails\VendorDocumentVerifiedMail;
use Modules\Vendor\Emails\VendorRejectedMail;
use Modules\Vendor\Emails\VendorSuspendedMail;
use Modules\Vendor\Emails\VendorWelcomeMail;
use Modules\Vendor\Events\VendorApprovedEvent;
use Modules\Vendor\Events\VendorDocumentRejectedEvent;
use Modules\Vendor\Events\VendorDocumentVerifiedEvent;
use Modules\Vendor\Events\VendorRegisteredEvent;
use Modules\Vendor\Events\VendorRejectedEvent;
use Modules\Vendor\Events\VendorSuspendedEvent;
use Modules\Vendor\Models\Vendor;
use Modules\Vendor\Models\VendorDocument;
use Throwable;

class SendVendorNotification
{
    /**
     * Handle vendor and KYC document lifecycle events.
     */
    public function handle(
        VendorRegisteredEvent|VendorApprovedEvent|VendorRejectedEvent|VendorSuspendedEvent|VendorDocumentVerifiedEvent|VendorDocumentRejectedEvent $event
    ): void {
        if ($event instanceof VendorDocumentVerifiedEvent || $event instanceof VendorDocumentRejectedEvent) {
            $document = $event->document->loadMissing(['vendor.user']);
            $vendor = $document->vendor;
            if (!$vendor) {
                return;
            }
            $user = $vendor->user ?? User::find($vendor->user_id);

            match ($event::class) {
                VendorDocumentVerifiedEvent::class => $this->handleDocumentVerified($document, $vendor, $user),
                VendorDocumentRejectedEvent::class => $this->handleDocumentRejected($document, $vendor, $user, $event->reason),
            };
            return;
        }

        $vendor = $event->vendor;
        $user = $vendor->user ?? User::find($vendor->user_id);

        match ($event::class) {
            VendorRegisteredEvent::class => $this->handleRegistered($vendor, $user, $event->skipVendorWelcomeMail),
            VendorApprovedEvent::class   => $this->handleApproved($vendor, $user),
            VendorRejectedEvent::class   => $this->handleRejected($vendor, $user, $event->reason ?? ''),
            VendorSuspendedEvent::class  => $this->handleSuspended($vendor, $user, $event->reason),
            default                      => null,
        };
    }

    // Hanlde Vendor Registered
    private function handleRegistered(Vendor $vendor, ?User $user, bool $skipVendorWelcomeMail = false): void
    {
        Log::info("Vendor registered: {$vendor->shop_name} (User: " . ($user?->id ?? 'unknown') . ")");

        // 1. Create in-app notification for admins
        try {
            $adminUsers = User::role(['super-admin', 'admin'])->get();
            if ($adminUsers->isEmpty()) {
                $fallback = User::find(1);
                if ($fallback) {
                    $adminUsers = collect([$fallback]);
                }
            }
            foreach ($adminUsers as $admin) {
                $this->createNotification($admin->id, 'vendor_registered', [
                    'title'      => 'New Vendor Registration',
                    'message'    => "New vendor registration: {$vendor->shop_name}",
                    'vendor_id'  => $vendor->id,
                    'action_url' => "/vendors/{$vendor->uuid}",
                    'type'       => 'vendor',
                ]);
            }
        } catch (Throwable $e) {
            Log::warning("Could not create admin notification: " . $e->getMessage());
        }

        // 2. Queue acknowledgement email to the vendor
        if (!$skipVendorWelcomeMail) {
            $this->queueVendorMail($vendor, $user, new VendorWelcomeMail($vendor));
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

    // Hanlde Vendor Approved
    private function handleApproved(Vendor $vendor, ?User $user): void
    {
        Log::info("Vendor approved: {$vendor->shop_name} (User: " . ($user?->id ?? 'unknown') . ")");

        $this->createNotification($user?->id, 'vendor_approved', [
            'title'      => 'Shop Application Approved',
            'message'    => "Congratulations! Your vendor shop '{$vendor->shop_name}' has been approved. You can now start selling.",
            'vendor_id'  => $vendor->id,
            'action_url' => '/vendor/dashboard',
            'type'       => 'vendor',
        ]);

        $this->queueVendorMail($vendor, $user, new VendorApprovedMail($vendor));
    }

    // Hanlde Vendor Rejected
    private function handleRejected(Vendor $vendor, ?User $user, string $reason): void
    {
        Log::info("Vendor rejected: {$vendor->shop_name} (Reason: {$reason})");

        $this->createNotification($user?->id, 'vendor_rejected', [
            'title'      => 'Shop Application Rejected',
            'message'    => "Your vendor application for '{$vendor->shop_name}' was rejected. Reason: {$reason}",
            'vendor_id'  => $vendor->id,
            'reason'     => $reason,
            'action_url' => '/my-account?tab=become-seller',
            'type'       => 'vendor',
        ]);

        $this->queueVendorMail($vendor, $user, new VendorRejectedMail($vendor, $reason));
    }

    // Hanlde Vendor Suspended
    private function handleSuspended(Vendor $vendor, ?User $user, ?string $reason): void
    {
        Log::info("Vendor suspended: {$vendor->shop_name} (Reason: " . ($reason ?? 'none') . ")");

        $reasonSuffix = $reason ? " Reason: {$reason}" : '';

        $this->createNotification($user?->id, 'vendor_suspended', [
            'title'      => 'Seller Account Suspended',
            'message'    => "Your vendor shop '{$vendor->shop_name}' has been suspended.{$reasonSuffix}",
            'vendor_id'  => $vendor->id,
            'reason'     => $reason,
            'action_url' => '/my-account?tab=become-seller',
            'type'       => 'vendor',
        ]);

        $this->queueVendorMail($vendor, $user, new VendorSuspendedMail($vendor, $reason));
    }

    // Hanlde Vendor Document Verified
    private function handleDocumentVerified(VendorDocument $document, Vendor $vendor, ?User $user): void
    {
        $docLabel = strtoupper(str_replace('_', ' ', $document->type));
        Log::info("Vendor document verified: {$docLabel} for {$vendor->shop_name}");

        $this->createNotification($user?->id, 'vendor_document_verified', [
            'title'       => 'KYC Document Verified',
            'message'     => "Your {$docLabel} document for '{$vendor->shop_name}' has been verified.",
            'vendor_id'   => $vendor->id,
            'document_id' => $document->id,
            'action_url'  => '/vendor/documents',
            'type'        => 'vendor',
        ]);

        $this->queueVendorMail($vendor, $user, new VendorDocumentVerifiedMail($document));
    }

    // Hanlde Vendor Document Rejected
    private function handleDocumentRejected(VendorDocument $document, Vendor $vendor, ?User $user, string $reason): void
    {
        $docLabel = strtoupper(str_replace('_', ' ', $document->type));
        Log::info("Vendor document rejected: {$docLabel} for {$vendor->shop_name} (Reason: {$reason})");

        $this->createNotification($user?->id, 'vendor_document_rejected', [
            'title'       => 'KYC Document Rejected',
            'message'     => "Your {$docLabel} document for '{$vendor->shop_name}' was rejected. Reason: {$reason}",
            'vendor_id'   => $vendor->id,
            'document_id' => $document->id,
            'reason'      => $reason,
            'action_url'  => '/vendor/documents',
            'type'        => 'vendor',
        ]);

        $this->queueVendorMail($vendor, $user, new VendorDocumentRejectedMail($document, $reason));
    }

    // Create in-app notification for a user
    private function createNotification(?int $userId, string $type, array $data): void
    {
        if (!$userId) {
            return;
        }

        try {
            Notification::create([
                'type'            => $type,
                'notifiable_type' => User::class,
                'notifiable_id'   => $userId,
                'data'            => $data,
            ]);
        } catch (Throwable $e) {
            Log::warning("Could not create notification ({$type}) for user {$userId}: " . $e->getMessage());
        }
    }

    // Queue an email to the vendor (or user) with error handling
    private function queueVendorMail(Vendor $vendor, ?User $user, Mailable $mailable): void
    {
        try {
            $email = $vendor->email ?: $user?->email;
            if ($email) {
                Mail::to($email)->queue($mailable);
            }
        } catch (Throwable $e) {
            Log::error("Failed to queue vendor email (" . $mailable::class . "): " . $e->getMessage());
        }
    }
}
