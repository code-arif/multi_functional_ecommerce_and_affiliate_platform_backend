<?php

namespace Modules\Vendor\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Vendor\Events\VendorRegisteredEvent;
use Modules\Vendor\Models\Vendor;
use Modules\Vendor\Models\VendorDocument;

class VendorService
{
    /**
     * Existing user applies to become a vendor.
     */
    public function apply(User $user, array $data): Vendor
    {
        if ($user->vendor()->exists()) {
            abort(400, 'You already have a registered shop or pending vendor application.');
        }

        return DB::transaction(function () use ($user, $data) {
            // Assign vendor role if not already assigned
            if (!$user->hasRole('vendor')) {
                $user->assignRole('vendor');
            }

            $shopName = $data['shop_name'];
            $slug = $this->generateUniqueSlug($shopName);

            $vendor = Vendor::create([
                'user_id'         => $user->id,
                'shop_name'       => $shopName,
                'slug'            => $slug,
                'email'           => $data['email'] ?? $user->email,
                'phone'           => $data['phone'] ?? $user->phone,
                'description'     => $data['description'] ?? null,
                'status'          => 'pending',
                'commission_rate' => config('ecommerce.vendor.default_commission_rate', 10),
                'commission_type' => config('ecommerce.vendor.default_commission_type', 'percentage'),
            ]);

            // Create VendorProfile if profile attributes are provided
            $profileData = array_filter([
                'business_type'                => $data['business_type'] ?? null,
                'business_registration_number' => $data['business_registration_number'] ?? null,
                'tax_id'                       => $data['tax_id'] ?? null,
                'website'                      => $data['website'] ?? null,
            ]);

            if (!empty($profileData)) {
                $vendor->profile()->create($profileData);
            }

            // Create VendorAddress if address details provided
            if (!empty($data['address_line_1']) && !empty($data['city'])) {
                $vendor->addresses()->create([
                    'label'          => 'Main Business Address',
                    'address_line_1' => $data['address_line_1'],
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'city'           => $data['city'],
                    'state'          => $data['state'] ?? null,
                    'postal_code'    => $data['postal_code'] ?? null,
                    'country'        => $data['country'] ?? 'Bangladesh',
                    'is_default'     => true,
                ]);
            }

            // Create VendorBankAccount if bank or mobile banking details provided
            if (!empty($data['account_number']) || !empty($data['mobile_banking_number'])) {
                $vendor->bankAccounts()->create([
                    'bank_name'               => $data['bank_name'] ?? null,
                    'branch_name'             => $data['branch_name'] ?? null,
                    'account_name'            => $data['account_name'] ?? $user->name,
                    'account_number'          => $data['account_number'] ?? null,
                    'routing_number'          => $data['routing_number'] ?? null,
                    'mobile_banking_provider' => $data['mobile_banking_provider'] ?? null,
                    'mobile_banking_number'   => $data['mobile_banking_number'] ?? null,
                    'is_default'              => true,
                ]);
            }

            VendorRegisteredEvent::dispatch($vendor);

            return $vendor->fresh(['profile', 'addresses', 'bankAccounts']);
        });
    }

    /**
     * Update vendor shop profile.
     */
    public function updateProfile(Vendor $vendor, array $data, ?UploadedFile $logo = null, ?UploadedFile $banner = null): Vendor
    {
        if ($logo) {
            $data['logo'] = $logo->store('vendors/logos', 'public');
        }
        if ($banner) {
            $data['banner'] = $banner->store('vendors/banners', 'public');
        }

        $vendor->update(array_filter([
            'shop_name'   => $data['shop_name'] ?? null,
            'email'       => $data['email'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'description' => $data['description'] ?? null,
            'logo'        => $data['logo'] ?? null,
            'banner'      => $data['banner'] ?? null,
        ]));

        $profileFields = array_filter([
            'business_type'   => $data['business_type'] ?? null,
            'website'         => $data['website'] ?? null,
            'return_policy'   => $data['return_policy'] ?? null,
            'shipping_policy' => $data['shipping_policy'] ?? null,
        ]);

        if (!empty($profileFields)) {
            $vendor->profile()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                $profileFields
            );
        }

        return $vendor->fresh(['profile', 'addresses']);
    }

    /**
     * Upload KYC verification document for vendor.
     */
    public function uploadDocument(Vendor $vendor, array $data, UploadedFile $file): VendorDocument
    {
        $path = $file->store("vendors/documents/{$vendor->id}", 'public');

        return $vendor->documents()->create([
            'type'            => $data['type'],
            'document_path'   => $path,
            'document_number' => $data['document_number'] ?? null,
            'expiry_date'     => $data['expiry_date'] ?? null,
            'status'          => 'pending',
        ]);
    }

    /**
     * Generate unique slug for vendor shop.
     */
    public function generateUniqueSlug(string $name): string
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
