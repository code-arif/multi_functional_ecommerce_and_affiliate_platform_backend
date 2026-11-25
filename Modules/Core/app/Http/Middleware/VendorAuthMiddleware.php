<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;

class VendorAuthMiddleware
{
    use ApiResponse;

    /**
     * Allow any authenticated user who owns a vendor shop (regardless of active/pending/suspended/rejected status)
     * so they can inspect status, upload KYC documents, and submit support tickets.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse('Authentication required.');
        }

        if (!$user->vendor) {
            return $this->forbiddenResponse('You are not registered as a vendor.');
        }

        return $next($request);
    }
}
