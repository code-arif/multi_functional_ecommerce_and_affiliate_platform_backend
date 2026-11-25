<?php

namespace Modules\Vendor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Vendor\Models\Vendor;
use Modules\Vendor\Models\VendorDocument;
use Modules\Vendor\Http\Requests\UpdateVendorStatusRequest;
use Modules\Vendor\Transformers\AdminVendorDocumentResource;
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
     * GET /api/v1/admin/vendors/stats
     * Summary counts for admin vendors dashboard & sidebar badges
     */
    public function stats(): JsonResponse
    {
        $counts = Vendor::selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        $pendingDocs = VendorDocument::where('status', 'pending')->count();
        $trashedCount = Vendor::onlyTrashed()->count();

        return $this->successResponse([
            'total'             => (int) ($counts->total ?? 0),
            'active'            => (int) ($counts->active ?? 0),
            'pending'           => (int) ($counts->pending ?? 0),
            'suspended'         => (int) ($counts->suspended ?? 0),
            'rejected'          => (int) ($counts->rejected ?? 0),
            'pending_documents' => $pendingDocs,
            'trashed'           => $trashedCount,
        ]);
    }

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
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->sort === 'oldest', fn($q) => $q->oldest(), fn($q) => $request->sort === 'name' ? $q->orderBy('shop_name') : $q->latest())
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
            ->withCount(['vendorProductPrices as products_count', 'orders as total_orders'])
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
     * Quick access to pending vendors (paginated)
     */
    public function pending(Request $request): JsonResponse
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
            ->pending()
            ->when($request->search, fn($q, $s) => $q->where('shop_name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse(AdminVendorListResource::collection($vendors));
    }

    /**
     * GET /api/v1/admin/vendors/documents
     * Centralized KYC document verification queue across all vendors
     */
    public function documents(Request $request): JsonResponse
    {
        $docs = VendorDocument::query()
            ->with(['vendor:id,uuid,shop_name,email,status'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('document_number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn($vq) => $vq->where('shop_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'rejected' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse(AdminVendorDocumentResource::collection($docs));
    }

    /**
     * PUT /api/v1/admin/vendors/{uuid}/commission
     * Update vendor commission rate & type
     */
    public function updateCommission(string $uuid, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_type' => 'required|in:percentage,fixed',
        ]);

        $vendor = Vendor::where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))->first();
        if (!$vendor) {
            return $this->notFoundResponse('Vendor not found.');
        }

        $vendor->update($validated);

        return $this->successResponse(
            new VendorResource($vendor->fresh()),
            'Vendor commission updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/vendors/{uuid}
     * Soft-delete a vendor
     */
    public function destroy(string $uuid): JsonResponse
    {
        $vendor = Vendor::where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))->first();
        if (!$vendor) {
            return $this->notFoundResponse('Vendor not found.');
        }

        $vendor->delete();

        return $this->noContentResponse('Vendor moved to trash successfully.');
    }

    /**
     * GET /api/v1/admin/vendors/trashed
     * List soft-deleted vendors
     */
    public function trashed(Request $request): JsonResponse
    {
        $vendors = Vendor::onlyTrashed()
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
                'deleted_at',
            ])
            ->with(['user:id,name'])
            ->withCount([
                'vendorProductPrices as products_count',
                'orders',
            ])
            ->withSum('orders', 'total_amount')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('shop_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('deleted_at')
            ->paginate($request->per_page ?? 20);

        return $this->paginatedResponse(AdminVendorListResource::collection($vendors));
    }

    /**
     * POST /api/v1/admin/vendors/{uuid}/restore
     * Restore a soft-deleted vendor
     */
    public function restore(string $uuid): JsonResponse
    {
        $vendor = Vendor::onlyTrashed()
            ->where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))
            ->first();

        if (!$vendor) {
            return $this->notFoundResponse('Deleted vendor not found.');
        }

        $vendor->restore();

        return $this->successResponse(
            new VendorResource($vendor->fresh()),
            'Vendor restored successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/vendors/{uuid}/force
     * Permanently delete a soft-deleted vendor
     */
    public function forceDelete(string $uuid): JsonResponse
    {
        $vendor = Vendor::onlyTrashed()
            ->where(fn($q) => $q->where('uuid', $uuid)->orWhere('id', $uuid))
            ->first();

        if (!$vendor) {
            return $this->notFoundResponse('Deleted vendor not found.');
        }

        $vendor->forceDelete();

        return $this->noContentResponse('Vendor permanently deleted.');
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
