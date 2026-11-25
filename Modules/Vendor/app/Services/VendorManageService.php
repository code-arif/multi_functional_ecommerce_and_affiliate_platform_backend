<?php
namespace Modules\Vendor\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

class VendorManageService
{
    /**
     * Admin creates a vendor: creates the user account, assigns the vendor role,
     * creates the vendor record as pending, and queues credentials email.
     */
    public function create(array $data): Vendor
    {
        $vendor = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);

            $user->assignRole('vendor');

            $shopName = $data['shop_name'] ?? $data['name'];

            return Vendor::create([
                'user_id' => $user->id,
                'shop_name' => $shopName,
                'slug' => $this->generateUniqueSlug($shopName),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => 'pending',
                'commission_rate' => config('ecommerce.vendor.default_commission_rate', 10),
                'commission_type' => config('ecommerce.vendor.default_commission_type', 'percentage'),
            ]);
        });

        // Notify admins without queuing a duplicate welcome email (password email is queued below)
        VendorRegisteredEvent::dispatch($vendor, true);

        // Queue welcome email with login credentials
        $this->queueWelcomeEmail($vendor, $data['password']);

        return $vendor;
    }

    /**
     * Approve vendor.
     */
    public function approve(Vendor $vendor, User $admin): Vendor
    {
        $vendor->update([
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $vendor = $vendor->fresh();

        VendorApprovedEvent::dispatch($vendor);

        return $vendor;
    }

    /**
     * Reject a vendor application with a reason.
     */
    public function reject(Vendor $vendor, string $reason): Vendor
    {
        $vendor->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $vendor = $vendor->fresh();

        VendorRejectedEvent::dispatch($vendor, $reason);

        return $vendor;
    }

    /**
     * Suspend an active vendor with an optional reason.
     */
    public function suspend(Vendor $vendor, ?string $reason = null): Vendor
    {
        $vendor->update([
            'status'           => 'suspended',
            'rejection_reason' => $reason,
        ]);

        $vendor = $vendor->fresh();

        VendorSuspendedEvent::dispatch($vendor, $reason);

        return $vendor;
    }

    /**
     * Verify a vendor KYC document and notify the vendor.
     */
    public function verifyDocument(VendorDocument $document, ?User $admin = null): VendorDocument
    {
        $document->update([
            'status'           => 'verified',
            'rejection_reason' => null,
            'verified_at'      => now(),
            'verified_by'      => $admin?->id,
        ]);

        $document = $document->fresh();

        VendorDocumentVerifiedEvent::dispatch($document);

        return $document;
    }

    /**
     * Reject a vendor KYC document with a reason and notify the vendor.
     */
    public function rejectDocument(VendorDocument $document, string $reason): VendorDocument
    {
        $document->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $document = $document->fresh();

        VendorDocumentRejectedEvent::dispatch($document, $reason);

        return $document;
    }

    private function queueWelcomeEmail(Vendor $vendor, string $password): void
    {
        try {
            Mail::to($vendor->email)->queue(new VendorWelcomeMail($vendor, $password));
        } catch (Throwable $e) {
            Log::warning("Failed to queue vendor welcome email: {$e->getMessage()}");
        }
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug     = Str::slug($name);
        $original = $slug;
        $count    = 1;

        while (Vendor::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $count;
            $count++;
        }

        return $slug;
    }
}
