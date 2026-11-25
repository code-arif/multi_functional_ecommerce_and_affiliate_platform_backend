<?php

namespace Modules\Vendor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Vendor\Models\Vendor;
use Modules\Vendor\Models\VendorDocument;
use Modules\Vendor\Http\Requests\UpdateVendorStatusRequest;
use Modules\Vendor\Transformers\AdminVendorListResource;
use Modules\Vendor\Transformers\VendorResource;
use Modules\Vendor\Transformers\VendorListResource;
use Modules\Vendor\Transformers\VendorDocumentResource;
use Modules\Vendor\Http\Requests\Admin\StoreVendorRequest;
use Modules\Vendor\Services\VendorManageService;
use Modules\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorManageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private VendorManageService $vendorManageService
    ) {}

    /**
     * GET /api/v1/admin/vendors
     * List all vendors for admin (lightweight table payload)
     */
    public function index(Request $request): JsonResponse
    {
        $vendors = Vendor::query()
            ->select([
                'id',
                'uuid',
                'user_id',
                'shop_name',
                'email',
                'phone',
                'logo',
                'status',
                'total_earned',
                'created_at',
            ])
            ->with(['user:id,name'])
            ->withCount([
                'vendorProductPrices as products_count',
                'orders',
            ])
            ->withSum('orders', 'total_amount')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where('shop_name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse(AdminVendorListResource::collection($vendors));
    }

    /**
     * POST /api/v1/admin/vendors
     * Create a vendor (user account + pending vendor record), then email credentials
     */
    public function store(StoreVendorRequest $request): JsonResponse
    {
        $vendor = $this->vendorManageService->create($request->validated());

        return $this->createdResponse(
            new VendorResource($vendor->load('user')),
            'Vendor created. A welcome email has been sent to the vendor.'
        );
    }

    /**
     * GET /api/v1/admin/vendors/{uuid}
     * Show vendor details with all relations
     */
    public function show(string $uuid): JsonResponse
    {
        $vendor = Vendor::with(['user', 'profile', 'addresses', 'bankAccounts', 'documents', 'staff.user'])
            ->where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))
            ->firstOrFail();
        return $this->successResponse(new VendorResource($vendor));
    }

    /**
     * POST /api/v1/admin/vendors/{uuid}/approve
     * Approve vendor application
     */
    public function approve(string $uuid, Request $request): JsonResponse
    {
        $vendor = Vendor::where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))->first();

        if(!$vendor){
            return $this->notFoundResponse('Vendor not found.');
        }

        $vendor = $this->vendorManageService->approve($vendor, $request->user());
        return $this->successResponse(
            new VendorResource($vendor->load('profile')),
            'Vendor approved successfully!'
        );
    }

    /**
     * POST /api/v1/admin/vendors/{uuid}/reject
     * Reject vendor application
     */
    public function reject(string $uuid, UpdateVendorStatusRequest $request): JsonResponse
    {
        $vendor = Vendor::where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))->first();

        if(!$vendor){
            return $this->notFoundResponse('Vendor not found.');
        }

        $vendor = $this->vendorManageService->reject($vendor, $request->validated('reason'));
        return $this->successResponse(
            new VendorResource($vendor),
            'Vendor rejected successfully!'
        );
    }

    /**
     * POST /api/v1/admin/vendors/{uuid}/suspend
     * Suspend an active vendor
     */
    public function suspend(string $uuid, UpdateVendorStatusRequest $request): JsonResponse
    {
        $vendor = Vendor::where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))->first();

        if(!$vendor){
            return $this->notFoundResponse('Vendor not found.');
        }

        $vendor = $this->vendorManageService->suspend($vendor, $request->validated('reason'));
        return $this->successResponse(
            new VendorResource($vendor),
            'Vendor suspended successfully!'
        );
    }

    /**
     * GET /api/v1/admin/vendors/pending
     * Quick access to pending vendors
     */
    public function pending(): JsonResponse
    {
        $vendors = Vendor::query()
            ->select([
                'id',
                'uuid',
                'user_id',
                'shop_name',
                'email',
                'phone',
                'logo',
                'status',
                'total_earned',
                'created_at',
            ])
            ->with(['user:id,name'])
            ->pending()
            ->latest()
            ->get();

        return $this->successResponse(AdminVendorListResource::collection($vendors));
    }

    /**
     * POST /api/v1/admin/vendors/{uuid}/pending
     * Transition vendor status back to pending
     */
    public function makePending(string $uuid): JsonResponse
    {
        $vendor = Vendor::where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))->first();

        if(!$vendor){
            return $this->notFoundResponse('Vendor not found.');
        }

        $vendor->update([
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return $this->successResponse(
            new VendorResource($vendor),
            'Vendor status updated to pending successfully!'
        );
    }

    /**
     * POST /api/v1/admin/vendors/documents/{document}/verify
     * Verify a vendor document
     */
    public function verifyDocument($document): JsonResponse
    {
        $doc = $document instanceof VendorDocument
            ? $document
            : VendorDocument::where(fn($q) => $q->where('uuid', $document)->orWhere('id', $document))->firstOrFail();

        $doc = $this->vendorManageService->verifyDocument($doc, request()->user());

        return $this->successResponse(
            new VendorDocumentResource($doc),
            'Document verified successfully.'
        );
    }

    /**
     * POST /api/v1/admin/vendors/documents/{document}/reject
     * Reject a vendor document
     */
    public function rejectDocument($document, UpdateVendorStatusRequest $request): JsonResponse
    {
        $doc = $document instanceof VendorDocument
            ? $document
            : VendorDocument::where(fn($q) => $q->where('uuid', $document)->orWhere('id', $document))->firstOrFail();

        $doc = $this->vendorManageService->rejectDocument($doc, $request->validated('reason'));

        return $this->successResponse(
            new VendorDocumentResource($doc),
            'Document rejected.'
        );
    }
}
