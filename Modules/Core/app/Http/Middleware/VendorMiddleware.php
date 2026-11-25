<?php

namespace Modules\Core\Http\Middleware;

use Modules\Core\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class VendorMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse('Authentication required.');
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            return $this->forbiddenResponse('You are not registered as a vendor.');
        }

        if ($vendor->status !== 'active') {
            $message = match ($vendor->status) {
                'pending'   => 'Your shop is currently pending verification or review.',
                'suspended' => 'Your shop has been suspended.',
                'rejected'  => 'Your vendor application was rejected.',
                default     => 'Your vendor account is not active.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'code'    => 'VENDOR_NOT_ACTIVE',
                'data'    => [
                    'status'           => $vendor->status,
                    'rejection_reason' => $vendor->rejection_reason,
                ],
            ], 403);
        }

        return $next($request);
    }
}
