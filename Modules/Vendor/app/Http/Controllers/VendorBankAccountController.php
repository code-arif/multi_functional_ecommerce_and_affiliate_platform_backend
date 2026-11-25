<?php

namespace Modules\Vendor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\Vendor\Models\VendorBankAccount;
use Modules\Vendor\Transformers\VendorBankAccountResource;

class VendorBankAccountController
{
    use ApiResponse;

    /**
     * GET /api/v1/vendor/bank-accounts
     * List all payout bank & mobile banking accounts for the authenticated vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        $accounts = $vendor->bankAccounts()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return $this->successResponse(VendorBankAccountResource::collection($accounts));
    }

    /**
     * POST /api/v1/vendor/bank-accounts
     * Add a new bank or mobile banking payout account.
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $validated = $this->validateAccount($request);

        if (empty($validated['account_number']) && empty($validated['mobile_banking_number'])) {
            return $this->errorResponse(
                'Provide either a bank account number or a mobile banking number.',
                null,
                422
            );
        }

        $account = DB::transaction(function () use ($vendor, $validated) {
            $hasExisting = $vendor->bankAccounts()->exists();
            $makeDefault = !$hasExisting || !empty($validated['is_default']);

            if ($makeDefault) {
                $vendor->bankAccounts()->update(['is_default' => false]);
            }

            return $vendor->bankAccounts()->create([
                'bank_name'               => $validated['bank_name'] ?? null,
                'branch_name'             => $validated['branch_name'] ?? null,
                'account_name'            => $validated['account_name'] ?? null,
                'account_number'          => $validated['account_number'] ?? null,
                'routing_number'          => $validated['routing_number'] ?? null,
                'swift_code'              => $validated['swift_code'] ?? null,
                'mobile_banking_provider' => $validated['mobile_banking_provider'] ?? null,
                'mobile_banking_number'   => $validated['mobile_banking_number'] ?? null,
                'is_default'              => $makeDefault,
            ]);
        });

        return $this->createdResponse(
            new VendorBankAccountResource($account),
            'Payout account added successfully.'
        );
    }

    /**
     * PUT /api/v1/vendor/bank-accounts/{bankAccount}
     * Update an existing payout account.
     */
    public function update(Request $request, VendorBankAccount $bankAccount): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if ($bankAccount->vendor_id !== $vendor->id) {
            return $this->notFoundResponse('Payout account not found.');
        }

        $validated = $this->validateAccount($request);

        if (empty($validated['account_number']) && empty($validated['mobile_banking_number'])) {
            return $this->errorResponse(
                'Provide either a bank account number or a mobile banking number.',
                null,
                422
            );
        }

        $updated = DB::transaction(function () use ($vendor, $bankAccount, $validated) {
            $makeDefault = $validated['is_default'] ?? $bankAccount->is_default;

            if ($makeDefault) {
                $vendor->bankAccounts()
                    ->where('id', '!=', $bankAccount->id)
                    ->update(['is_default' => false]);
            }

            $bankAccount->update([
                'bank_name'               => $validated['bank_name'] ?? null,
                'branch_name'             => $validated['branch_name'] ?? null,
                'account_name'            => $validated['account_name'] ?? null,
                'account_number'          => $validated['account_number'] ?? null,
                'routing_number'          => $validated['routing_number'] ?? null,
                'swift_code'              => $validated['swift_code'] ?? null,
                'mobile_banking_provider' => $validated['mobile_banking_provider'] ?? null,
                'mobile_banking_number'   => $validated['mobile_banking_number'] ?? null,
                'is_default'              => (bool) $makeDefault,
            ]);

            return $bankAccount->fresh();
        });

        return $this->successResponse(
            new VendorBankAccountResource($updated),
            'Payout account updated successfully.'
        );
    }

    /**
     * POST /api/v1/vendor/bank-accounts/{bankAccount}/default
     * Set a payout account as default.
     */
    public function setDefault(Request $request, VendorBankAccount $bankAccount): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if ($bankAccount->vendor_id !== $vendor->id) {
            return $this->notFoundResponse('Payout account not found.');
        }

        DB::transaction(function () use ($vendor, $bankAccount) {
            $vendor->bankAccounts()->update(['is_default' => false]);
            $bankAccount->update(['is_default' => true]);
        });

        return $this->successResponse(
            new VendorBankAccountResource($bankAccount->fresh()),
            'Default payout account updated.'
        );
    }

    /**
     * DELETE /api/v1/vendor/bank-accounts/{bankAccount}
     * Delete a payout account.
     */
    public function destroy(Request $request, VendorBankAccount $bankAccount): JsonResponse
    {
        $vendor = $request->user()->vendor;

        if ($bankAccount->vendor_id !== $vendor->id) {
            return $this->notFoundResponse('Payout account not found.');
        }

        DB::transaction(function () use ($vendor, $bankAccount) {
            $wasDefault = $bankAccount->is_default;
            $bankAccount->delete();

            if ($wasDefault) {
                $next = $vendor->bankAccounts()->latest()->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }
        });

        return $this->successResponse(null, 'Payout account deleted.');
    }

    private function validateAccount(Request $request): array
    {
        return $request->validate([
            'bank_name'               => 'nullable|string|max:200',
            'branch_name'             => 'nullable|string|max:200',
            'account_name'            => 'nullable|string|max:200',
            'account_number'          => 'nullable|string|max:100',
            'routing_number'          => 'nullable|string|max:50',
            'swift_code'              => 'nullable|string|max:50',
            'mobile_banking_provider' => 'nullable|string|max:50',
            'mobile_banking_number'   => 'nullable|string|max:50',
            'is_default'              => 'nullable|boolean',
        ]);
    }
}
