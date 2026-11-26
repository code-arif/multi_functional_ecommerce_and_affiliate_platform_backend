<?php

namespace Modules\RBAC\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Transformers\UserResource;
use Modules\Core\Traits\ApiResponse;
use Modules\RBAC\Http\Requests\AssignRoleRequest;
use Modules\RBAC\Services\RBACService;

class UserRoleController
{
    use ApiResponse;

    public function __construct(private RBACService $rbacService) {}

    /**
     * List users with their roles, with optional role filter.
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->rbacService->listUsersWithRoles($request->only([
            'search', 'role', 'sort', 'order', 'per_page',
        ]));

        return $this->paginatedResponse(UserResource::collection($users));
    }

    /**
     * Assign (sync) roles to a user.
     */
    public function assign(AssignRoleRequest $request): JsonResponse
    {
        $user = $this->rbacService->assignRolesToUser(
            User::findByUuidOrFail($request->validated('user_uuid'))->id,
            $request->validated('roles'),
            $request->user()
        );

        return $this->successResponse([
            'user'  => [
                'uuid'  => $user->uuid,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'roles' => $user->roles->pluck('name'),
        ], 'Roles assigned successfully.');
    }

    /**
     * Get all permissions for a specific user.
     */
    public function userPermissions(string $userId): JsonResponse
    {
        $permissions = $this->rbacService->getUserPermissions(
            User::findByUuidOrFail($userId)->id
        );
        return $this->successResponse($permissions->pluck('name'));
    }

    /**
     * Get roles & effective permissions of the currently authenticated user.
     */
    public function myRoles(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');
        return $this->successResponse([
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()
                ->pluck('name')
                ->unique()
                ->values(),
        ]);
    }
}
