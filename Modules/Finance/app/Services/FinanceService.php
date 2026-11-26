<?php

namespace Modules\Finance\Services;

use Modules\Finance\Models\Commission;
use Modules\Finance\Models\VendorPayoutRequest;
use Modules\Finance\Models\VendorSettlement;
use Modules\Orders\Models\Order;
use Modules\Vendor\Models\Vendor;
use Modules\Vendor\Models\VendorWalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinanceService
{
    // ─── Commission Management ─────────────────────────────────────

    /**
     * Calculate and create a commission record for a delivered order.
     */
    public function calculateCommission(Order $order): ?Commission
    {
        if (!$order->vendor_id) {
            return null; // General products (no vendor commission)
        }

        $vendor = $order->vendor;
        if (!$vendor || $vendor->status !== 'active') {
            return null;
        }

        // Prevent duplicate commission calculation
        if (Commission::where('order_id', $order->id)->where('vendor_id', $vendor->id)->exists()) {
            return null;
        }

        $commissionAmount = Commission::calculate(
            $order->total,
            $vendor->commission_rate,
            $vendor->commission_type
        );

        if ($commissionAmount <= 0) {
            return null;
        }

        return Commission::create([
            'order_id'         => $order->id,
            'vendor_id'        => $vendor->id,
            'order_total'      => $order->total,
            'commission_rate'  => $vendor->commission_rate,
            'commission_type'  => $vendor->commission_type,
            'commission_amount' => $commissionAmount,
            'status'           => 'pending',
        ]);
    }

    /**
     * Approve a commission and credit the vendor's net earnings (order_total - commission_amount) to their wallet.
     */
    public function approveCommission(Commission $commission): Commission
    {
        if ($commission->status !== 'pending') {
            abort(400, 'Commission has already been ' . $commission->status . '.');
        }

        return DB::transaction(function () use ($commission) {
            $lockedCommission = Commission::whereKey($commission->id)->lockForUpdate()->firstOrFail();

            if ($lockedCommission->status !== 'pending') {
                abort(400, 'Commission has already been ' . $lockedCommission->status . '.');
            }

            $vendor = Vendor::whereKey($lockedCommission->vendor_id)->lockForUpdate()->firstOrFail();
            $netEarning = round(max(0, (float) $lockedCommission->order_total - (float) $lockedCommission->commission_amount), 2);

            $lockedCommission->update([
                'status'      => 'approved',
                'approved_at' => now(),
            ]);

            $before = (float) $vendor->wallet_balance;
            $after  = round($before + $netEarning, 2);

            VendorWalletTransaction::create([
                'vendor_id'      => $vendor->id,
                'type'           => 'commission',
                'amount'         => $netEarning,
                'balance_before' => $before,
                'balance_after'  => $after,
                'description'    => "Order #{$lockedCommission->order->order_number} earnings (Fee: {$lockedCommission->commission_amount})",
                'reference_type' => 'order',
                'reference_id'   => $lockedCommission->order_id,
                'status'         => 'completed',
            ]);

            $vendor->increment('wallet_balance', $netEarning);
            $vendor->increment('total_earned', $netEarning);

            Log::info('Commission approved', [
                'commission_id'     => $lockedCommission->id,
                'vendor_id'         => $vendor->id,
                'commission_amount' => $lockedCommission->commission_amount,
                'vendor_net_credit' => $netEarning,
            ]);

            return $lockedCommission->fresh();
        });
    }

    /**
     * Get commissions for a vendor.
     */
    public function getVendorCommissions(int $vendorId, array $filters = [])
    {
        $query = Commission::with('order')
            ->where('vendor_id', $vendorId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get all commissions (admin view).
     */
    public function getAllCommissions(array $filters = [])
    {
        $query = Commission::with(['vendor:id,shop_name', 'order']);

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    // ─── Payout Management ─────────────────────────────────────────

    /**
     * Vendor requests a payout (row-locked to prevent concurrent wallet overdraft).
     */
    public function requestPayout(Vendor $vendor, float $amount, array $data = []): VendorPayoutRequest
    {
        if ($amount < 100) {
            abort(400, 'Minimum payout amount is 100.');
        }

        return DB::transaction(function () use ($vendor, $amount, $data) {
            $lockedVendor = Vendor::whereKey($vendor->id)->lockForUpdate()->firstOrFail();

            if ((float) $lockedVendor->wallet_balance < $amount) {
                abort(400, 'Insufficient wallet balance.');
            }

            $before = (float) $lockedVendor->wallet_balance;
            $after  = round($before - $amount, 2);

            $payout = VendorPayoutRequest::create([
                'vendor_id'       => $lockedVendor->id,
                'amount'          => $amount,
                'balance_before'  => $before,
                'balance_after'   => $after,
                'payment_method'  => $data['payment_method'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => 'pending',
            ]);

            // Hold the amount in wallet (reduce available balance)
            $lockedVendor->decrement('wallet_balance', $amount);

            VendorWalletTransaction::create([
                'vendor_id'       => $lockedVendor->id,
                'type'            => 'payout',
                'amount'          => -$amount,
                'balance_before'  => $before,
                'balance_after'   => $after,
                'description'     => "Payout request #{$payout->id}",
                'reference_type'  => 'payout',
                'reference_id'    => $payout->id,
                'status'          => 'pending',
            ]);

            return $payout;
        });
    }

    /**
     * Admin approves a payout request.
     */
    public function approvePayout(VendorPayoutRequest $payout, User $admin): VendorPayoutRequest
    {
        return DB::transaction(function () use ($payout, $admin) {
            $lockedPayout = VendorPayoutRequest::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayout->status !== 'pending') {
                abort(400, 'Only pending payout requests can be approved.');
            }

            $lockedPayout->update([
                'status'      => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $lockedPayout->vendor()->increment('total_withdrawn', $lockedPayout->amount);

            Log::info('Payout approved', [
                'payout_id' => $lockedPayout->id,
                'vendor_id' => $lockedPayout->vendor_id,
                'amount'    => $lockedPayout->amount,
            ]);

            return $lockedPayout->fresh();
        });
    }

    /**
     * Admin rejects a payout request (refund to wallet).
     */
    public function rejectPayout(VendorPayoutRequest $payout, User $admin, string $reason): VendorPayoutRequest
    {
        return DB::transaction(function () use ($payout, $admin, $reason) {
            $lockedPayout = VendorPayoutRequest::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedPayout->status, ['pending', 'approved'], true)) {
                abort(400, 'This payout request can no longer be rejected.');
            }

            $vendor = Vendor::whereKey($lockedPayout->vendor_id)->lockForUpdate()->firstOrFail();
            $before = (float) $vendor->wallet_balance;
            $after  = round($before + (float) $lockedPayout->amount, 2);

            $vendor->increment('wallet_balance', $lockedPayout->amount);
            if ($lockedPayout->status === 'approved') {
                $vendor->decrement('total_withdrawn', $lockedPayout->amount);
            }

            VendorWalletTransaction::create([
                'vendor_id'       => $vendor->id,
                'type'            => 'adjustment',
                'amount'          => $lockedPayout->amount,
                'balance_before'  => $before,
                'balance_after'   => $after,
                'description'     => "Payout #{$lockedPayout->id} rejected: {$reason}",
                'reference_type'  => 'payout',
                'reference_id'    => $lockedPayout->id,
                'status'          => 'completed',
            ]);

            $lockedPayout->update([
                'status'       => 'rejected',
                'approved_by'  => $admin->id,
                'approved_at'  => now(),
                'admin_notes'  => $reason,
            ]);

            return $lockedPayout->fresh();
        });
    }

    /**
     * Mark payout as completed (disbursed).
     */
    public function completePayout(VendorPayoutRequest $payout): VendorPayoutRequest
    {
        return DB::transaction(function () use ($payout) {
            $lockedPayout = VendorPayoutRequest::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayout->status !== 'approved') {
                abort(400, 'Only approved payout requests can be marked as completed.');
            }

            $lockedPayout->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            VendorWalletTransaction::where('reference_type', 'payout')
                ->where('reference_id', $lockedPayout->id)
                ->update(['status' => 'completed']);

            return $lockedPayout->fresh();
        });
    }

    /**
     * Get payout requests for a vendor.
     */
    public function getVendorPayouts(int $vendorId, array $filters = [])
    {
        $query = VendorPayoutRequest::where('vendor_id', $vendorId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get all payout requests (admin view).
     */
    public function getAllPayouts(array $filters = [])
    {
        $query = VendorPayoutRequest::with('vendor:id,uuid,shop_name,email,commission_rate,wallet_balance');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    // ─── Settlement Reports ────────────────────────────────────────

    /**
     * Generate a settlement report for a vendor for a given period.
     */
    public function generateSettlement(Vendor $vendor, string $startDate, string $endDate): VendorSettlement
    {
        $orders = Order::where('vendor_id', $vendor->id)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$startDate, $endDate . ' 23:59:59'])
            ->get();

        $totalSales = $orders->sum('total');

        $commissions = Commission::where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('status', 'approved')
            ->get();

        $totalCommission = $commissions->sum('commission_amount');

        $payouts = VendorPayoutRequest::where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate . ' 23:59:59'])
            ->get();

        $totalPaid = $payouts->sum('amount');

        return VendorSettlement::create([
            'vendor_id'       => $vendor->id,
            'period_label'    => date('F Y', strtotime($startDate)),
            'period_start'    => $startDate,
            'period_end'      => $endDate,
            'total_sales'     => $totalSales,
            'total_commission' => $totalCommission,
            'net_earnings'    => $totalSales - $totalCommission,
            'total_paid'      => $totalPaid,
            'balance_carried' => ($totalSales - $totalCommission) - $totalPaid,
            'status'          => 'draft',
        ]);
    }

    /**
     * Finalize a settlement report.
     */
    public function finalizeSettlement(VendorSettlement $settlement): VendorSettlement
    {
        $settlement->update([
            'status'       => 'finalized',
            'finalized_at' => now(),
        ]);

        return $settlement->fresh();
    }

    /**
     * Get settlements for a vendor.
     */
    public function getVendorSettlements(int $vendorId)
    {
        return VendorSettlement::where('vendor_id', $vendorId)
            ->latest('period_end')
            ->paginate(20);
    }

    /**
     * Get wallet transaction history for a vendor.
     */
    public function getWalletTransactions(int $vendorId, array $filters = [])
    {
        $query = VendorWalletTransaction::where('vendor_id', $vendorId);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get vendor wallet summary.
     */
    public function getWalletSummary(Vendor $vendor): array
    {
        return [
            'balance'         => (float) $vendor->wallet_balance,
            'total_earned'    => (float) $vendor->total_earned,
            'total_withdrawn' => (float) $vendor->total_withdrawn,
            'pending_payouts' => (float) VendorPayoutRequest::where('vendor_id', $vendor->id)
                ->where('status', 'pending')->sum('amount'),
            'available'       => max(0, (float) $vendor->wallet_balance),
        ];
    }
}
