<?php

namespace Modules\RBAC\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\RBAC\Http\Requests\StoreAdminStaffRequest;
use Modules\RBAC\Http\Requests\UpdateAdminStaffRequest;
use Modules\RBAC\Http\Requests\UpdateAdminStatusRequest;
use Modules\RBAC\Services\RBACService;
use Modules\RBAC\Transformers\AdminStaffResource;

class AdminStaffController
{
    use ApiResponse;

    public function __construct(private RBACService $rbacService) {}

    public function index(Request $request): JsonResponse
    {
        $admins = $this->rbacService->listAdminStaff(
            $request->only(['search', 'role', 'status', 'per_page'])
        );

        return $this->paginatedResponse(AdminStaffResource::collection($admins));
    }

    public function stats(): JsonResponse
    {
        return $this->successResponse($this->rbacService->adminStaffStats());
    }

    public function show(string $admin): JsonResponse
    {
        $record = $this->rbacService->findAdminStaff($admin);
        $logs = $this->rbacService->getAdminActivityLogs($record);

        $data = (new AdminStaffResource($record))->toArray(request());
        $data['activity_logs'] = $logs->map(fn ($log) => [
            'id'         => $log->id,
            'module'     => $log->module,
            'action'     => $log->action,
            'ip'         => $log->ip,
            'created_at' => $log->created_at,
        ])->values();

        return $this->successResponse($data);
    }

    public function store(StoreAdminStaffRequest $request): JsonResponse
    {
        $admin = $this->rbacService->createAdminStaff($request->validated(), $request->user());

        return $this->createdResponse(
            new AdminStaffResource($admin),
            'Admin account created successfully.'
        );
    }

    public function update(UpdateAdminStaffRequest $request, string $admin): JsonResponse
    {
        $record = $this->rbacService->findAdminStaff($admin);
        $updated = $this->rbacService->updateAdminStaff($record, $request->validated(), $request->user());

        return $this->successResponse(
            new AdminStaffResource($updated),
            'Admin account updated successfully.'
        );
    }

    public function updateStatus(UpdateAdminStatusRequest $request, string $admin): JsonResponse
    {
        $record = $this->rbacService->findAdminStaff($admin);
        $status = $request->validated('status');
        $updated = $this->rbacService->updateAdminStatus($record, $status, $request->user());

        return $this->successResponse(
            new AdminStaffResource($updated),
            "Admin status updated to {$status}."
        );
    }

    public function destroy(Request $request, string $admin): JsonResponse
    {
        $record = $this->rbacService->findAdminStaff($admin);
        $this->rbacService->deleteAdminStaff($record, $request->user());

        return $this->noContentResponse('Admin account deleted successfully.');
    }
}
