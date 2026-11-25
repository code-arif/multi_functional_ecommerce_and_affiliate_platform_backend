<?php

namespace Modules\Vendor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\Vendor\Models\VendorAddress;
use Modules\Vendor\Transformers\VendorAddressResource;

class VendorAddressController
{
    use ApiResponse;

    /**
     * GET /api/v1/vendor/addresses
     * List all business / pickup / return addresses for the authenticated vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        $addresses = $vendor->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return $this->successResponse(VendorAddressResource::collection($addresses));
    }

    /**
     * POST /api/v1/vendor/addresses
     * Add a new shop address.
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $validated = $this->validateAddress($request);

        $address = DB::transaction(function () use ($vendor, $validated) {
            $hasExisting = $vendor->addresses()->exists();
            $makeDefault = !$hasExisting || !empty($validated['is_default']);

            if ($makeDefault) {
                $vendor->addresses()->update(['is_default' => false]);
            }

            return $vendor->addresses()->create([
                'label'          => $validated['label'] ?? 'Main Address',
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city'           => $validated['city'],
                'state'          => $validated['state'] ?? null,
                'postal_code'    => $validated['postal_code'] ?? null,
                'country'        => $validated['country'] ?? 'Bangladesh',
                'latitude'       => $validated['latitude'] ?? null,
                'longitude'      => $validated['longitude'] ?? null,
                'is_default'     => $makeDefault,
            ]);
        });

        return $this->createdResponse(
            new VendorAddressResource($address),
            'Address added successfully.'
        );
    }

    /**
     * PUT /api/v1/vendor/addresses/{address}
     * Update an existing shop address.
     */
    public function update(Request $request, VendorAddress $address): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if ($address->vendor_id !== $vendor->id) {
            return $this->notFoundResponse('Address not found.');
        }

        $validated = $this->validateAddress($request);

        $updated = DB::transaction(function () use ($vendor, $address, $validated) {
            $makeDefault = $validated['is_default'] ?? $address->is_default;

            if ($makeDefault) {
                $vendor->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update([
                'label'          => $validated['label'] ?? $address->label,
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city'           => $validated['city'],
                'state'          => $validated['state'] ?? null,
                'postal_code'    => $validated['postal_code'] ?? null,
                'country'        => $validated['country'] ?? 'Bangladesh',
                'latitude'       => $validated['latitude'] ?? null,
                'longitude'      => $validated['longitude'] ?? null,
                'is_default'     => (bool) $makeDefault,
            ]);

            return $address->fresh();
        });

        return $this->successResponse(
            new VendorAddressResource($updated),
            'Address updated successfully.'
        );
    }

    /**
     * POST /api/v1/vendor/addresses/{address}/default
     * Set an address as default.
     */
    public function setDefault(Request $request, VendorAddress $address): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if ($address->vendor_id !== $vendor->id) {
            return $this->notFoundResponse('Address not found.');
        }

        DB::transaction(function () use ($vendor, $address) {
            $vendor->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return $this->successResponse(
            new VendorAddressResource($address->fresh()),
            'Default address updated.'
        );
    }

    /**
     * DELETE /api/v1/vendor/addresses/{address}
     * Delete a shop address.
     */
    public function destroy(Request $request, VendorAddress $address): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if ($address->vendor_id !== $vendor->id) {
            return $this->notFoundResponse('Address not found.');
        }

        DB::transaction(function () use ($vendor, $address) {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $next = $vendor->addresses()->latest()->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }
        });

        return $this->successResponse(null, 'Address deleted.');
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label'          => 'nullable|string|max:50',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city'           => 'required|string|max:100',
            'state'          => 'nullable|string|max:100',
            'postal_code'    => 'nullable|string|max:20',
            'country'        => 'nullable|string|max:100',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
            'is_default'     => 'nullable|boolean',
        ]);
    }
}
