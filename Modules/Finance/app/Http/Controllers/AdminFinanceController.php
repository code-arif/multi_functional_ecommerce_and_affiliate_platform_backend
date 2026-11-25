<?php

namespace Modules\Finance\Http\Controllers;

use Modules\Finance\Services\FinanceService;
use Modules\Finance\Models\Commission;
use Modules\Finance\Models\VendorPayoutRequest;
use Modules\Finance\Models\VendorSettlement;
use Modules\Finance\Http\Resources\CommissionResource;
use Modules\Finance\Http\Resources\PayoutResource;
use Modules\Finance\Http\Resources\SettlementResource;
use Modules\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFinanceController
{
    use ApiResponse;

    public function __construct(private FinanceService $financeService) {}

    // ─── Commissions ──────────────────────────────────────────────

    public function commissions(Request $request): JsonResponse
    {
        $filters = $request->only(['vendor_uuid', 'status', 'per_page']);
        if (!empty($filters['vendor_uuid'])) {
            $filters['vendor_id'] = \Modules\Vendor\Models\Vendor::findByUuid($filters['vendor_uuid'])?->id;
        }
        unset($filters['vendor_uuid']);

        $commissions = $this->financeService->getAllCommissions($filters);
        return $this->paginatedResponse(CommissionResource::collection($commissions));
    }

    public function approveCommission(Commission $commission): JsonResponse
    {
        $commission = $this->financeService->approveCommission($commission);
        return $this->successResponse(new CommissionResource($commission), 'Commission approved.');
    }

    // ─── Payouts ──────────────────────────────────────────────────

    public function payouts(Request $request): JsonResponse
    {
        $filters = $request->only(['vendor_uuid', 'status', 'date_from', 'date_to', 'per_page']);
        if (!empty($filters['vendor_uuid'])) {
            $filters['vendor_id'] = \Modules\Vendor\Models\Vendor::findByUuid($filters['vendor_uuid'])?->id;
        }
        unset($filters['vendor_uuid']);

        $payouts = $this->financeService->getAllPayouts($filters);
        return $this->paginatedResponse(PayoutResource::collection($payouts));
    }

    public function payoutSummary(): JsonResponse
    {
        $totals = \Modules\Vendor\Models\Vendor::selectRaw("
                COALESCE(SUM(total_earned), 0) as total_earned,
                COALESCE(SUM(total_withdrawn), 0) as total_withdrawn,
                COALESCE(SUM(wallet_balance), 0) as pending_balance,
                SUM(CASE WHEN wallet_balance > 0 THEN 1 ELSE 0 END) as vendors_due
            ")
            ->first();

        $pendingRequests = VendorPayoutRequest::where('status', 'pending')->count();
        $completedPayouts = (float) VendorPayoutRequest::where('status', 'completed')->sum('amount');

        return $this->successResponse([
            'total_earned'          => (float) ($totals->total_earned ?? 0),
            'total_withdrawn'       => (float) ($totals->total_withdrawn ?? 0),
            'pending_balance'       => (float) ($totals->pending_balance ?? 0),
            'vendors_due'           => (int) ($totals->vendors_due ?? 0),
            'pending_requests'      => $pendingRequests,
            'completed_payouts_sum' => $completedPayouts,
        ]);
    }

    public function approvePayout(VendorPayoutRequest $payout, Request $request): JsonResponse
    {
        $payout = $this->financeService->approvePayout($payout, $request->user());
        return $this->successResponse(new PayoutResource($payout), 'Payout approved.');
    }

    public function rejectPayout(VendorPayoutRequest $payout, Request $request): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);
        $payout = $this->financeService->rejectPayout($payout, $request->user(), $validated['reason']);
        return $this->successResponse(new PayoutResource($payout), 'Payout rejected.');
    }

    public function completePayout(VendorPayoutRequest $payout): JsonResponse
    {
        $payout = $this->financeService->completePayout($payout);
        return $this->successResponse(new PayoutResource($payout), 'Payout completed.');
    }

    // ─── Settlements ──────────────────────────────────────────────

    public function generateSettlement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_uuid' => 'required|exists:vendors,uuid',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $vendor = \Modules\Vendor\Models\Vendor::findByUuidOrFail($validated['vendor_uuid']);
        $settlement = $this->financeService->generateSettlement($vendor, $validated['start_date'], $validated['end_date']);

        return $this->createdResponse(new SettlementResource($settlement), 'Settlement generated.');
    }

    public function finalizeSettlement(VendorSettlement $settlement): JsonResponse
    {
        $settlement = $this->financeService->finalizeSettlement($settlement);
        return $this->successResponse(new SettlementResource($settlement), 'Settlement finalized.');
    }

    public function settlements(Request $request): JsonResponse
    {
        $query = VendorSettlement::with('vendor:id,shop_name');
        if ($request->vendor_uuid) {
            $query->where('vendor_id', \Modules\Vendor\Models\Vendor::findByUuid($request->vendor_uuid)?->id);
        }

        return $this->paginatedResponse(SettlementResource::collection($query->latest()->paginate(20)));
    }
}
