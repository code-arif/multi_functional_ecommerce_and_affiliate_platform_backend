<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Services\ReportService;
use Modules\Core\Traits\ApiResponse;

class ReportController
{
    use ApiResponse;

    public function __construct(private ReportService $reportService) {}

    public function sales(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->reportService->sales($request->only(['from', 'to', 'status']))
        );
    }

    public function topProducts(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->reportService->topProducts($request->limit ?? 10)
        );
    }

    public function ordersByStatus(): JsonResponse
    {
        return $this->successResponse($this->reportService->ordersByStatus());
    }

    public function customerGrowth(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->reportService->customerGrowth($request->period ?? 'monthly')
        );
    }
}
