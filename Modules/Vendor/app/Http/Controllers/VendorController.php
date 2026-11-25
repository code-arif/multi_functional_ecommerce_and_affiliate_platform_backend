<?php

namespace Modules\Vendor\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Vendor\Http\Requests\ApplyVendorRequest;
use Modules\Vendor\Http\Requests\UpdateVendorRequest;
use Modules\Vendor\Http\Requests\UploadDocumentRequest;
use Modules\Vendor\Models\Vendor;
use Modules\Vendor\Models\VendorDocument;
use Modules\Vendor\Services\VendorService;
use Modules\Vendor\Transformers\VendorDocumentResource;
use Modules\Vendor\Transformers\VendorListResource;
use Modules\Vendor\Transformers\VendorResource;

class VendorController
{
    use ApiResponse;

    public function __construct(
        private VendorService $vendorService
    ) {}

    /**
     * POST /api/v1/vendor/apply
     * Existing authenticated customer applies to become a vendor.
     */
    public function apply(ApplyVendorRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->vendor()->exists()) {
            return $this->errorResponse('You already have a registered shop or pending vendor application.', null, 400);
        }

        $vendor = $this->vendorService->apply($user, $request->validated());

        return $this->createdResponse(
            new VendorResource($vendor),
            'Seller application submitted successfully. Your shop is pending approval.'
        );
    }

    /**
     * GET /api/v1/vendor/profile
     * Get authenticated vendor's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor?->load(['profile', 'addresses', 'bankAccounts', 'documents']);

        if (!$vendor) {
            return $this->notFoundResponse('Vendor profile not found.');
        }

        return $this->successResponse(new VendorResource($vendor));
    }

    /**
     * PUT /api/v1/vendor/profile
     * Update vendor shop profile.
     */
    public function updateProfile(UpdateVendorRequest $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return $this->notFoundResponse('Vendor profile not found.');
        }

        $updatedVendor = $this->vendorService->updateProfile(
            $vendor,
            $request->validated(),
            $request->file('logo'),
            $request->file('banner')
        );

        return $this->successResponse(
            new VendorResource($updatedVendor),
            'Profile updated successfully.'
        );
    }

    /**
     * POST /api/v1/vendor/documents
     * Upload KYC documents for verification.
     */
    public function uploadDocument(UploadDocumentRequest $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return $this->notFoundResponse('Vendor profile not found.');
        }

        $document = $this->vendorService->uploadDocument(
            $vendor,
            $request->validated(),
            $request->file('document')
        );

        return $this->createdResponse(
            new VendorDocumentResource($document),
            'Document uploaded successfully for verification.'
        );
    }

    /**
     * GET /api/v1/vendor/documents
     * List vendor's uploaded KYC documents.
     */
    public function documents(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return $this->notFoundResponse('Vendor profile not found.');
        }

        $docs = $vendor->documents()->latest()->get();

        return $this->successResponse(
            VendorDocumentResource::collection($docs)
        );
    }

    /**
     * DELETE /api/v1/vendor/documents/{document}
     * Delete a pending or rejected KYC document.
     */
    public function destroyDocument(Request $request, VendorDocument $document): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if (!$vendor || $document->vendor_id !== $vendor->id) {
            return $this->notFoundResponse('Document not found.');
        }

        if ($document->status === 'verified') {
            return $this->errorResponse('Verified documents cannot be deleted.', null, 422);
        }

        $this->vendorService->deleteDocument($document);

        return $this->successResponse(null, 'Document deleted successfully.');
    }

    /**
     * GET /api/v1/vendors
     * Public vendor listing (active shops).
     */
    public function index(Request $request): JsonResponse
    {
        $vendors = Vendor::active()
            ->with('profile')
            ->when($request->search, fn($q, $s) => $q->where('shop_name', 'like', "%{$s}%"))
            ->orderBy('shop_name')
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse(VendorListResource::collection($vendors));
    }

    /**
     * GET /api/v1/vendors/{slug}
     * Public vendor shop details by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $vendor = Vendor::active()
            ->with(['profile', 'addresses'])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->successResponse(new VendorResource($vendor));
    }
}
