<?php

use Illuminate\Support\Facades\Route;
use Modules\RBAC\Http\Controllers\AdminStaffController;
use Modules\RBAC\Http\Controllers\PermissionController;
use Modules\RBAC\Http\Controllers\RoleController;
use Modules\RBAC\Http\Controllers\UserRoleController;

/*
|--------------------------------------------------------------------------
| RBAC Module API Routes
|
| Prefix: api/v1/admin/rbac (and api/v1/auth/my-permissions)
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'v1/admin', 'middleware' => ['auth:sanctum', 'admin', 'banned']], function () {

    Route::group(['prefix' => 'rbac'], function () {

        // Roles
        Route::group(['prefix' => 'roles'], function () {
            Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view'); // DONE: Role list (RolesPage, AdminsPage, AdminDetailPage)
            Route::post('/store', [RoleController::class, 'store'])->middleware('permission:roles.manage'); // DONE: Role create (RoleFormPage)
            Route::get('/{id}/show', [RoleController::class, 'show'])->middleware('permission:roles.view'); // DONE: Get role details (RoleFormPage, RoleDetailPage)
            Route::put('/{id}/update', [RoleController::class, 'update'])->middleware('permission:roles.manage'); // DONE: Update role & permissions (RoleFormPage)
            Route::delete('/{id}/destroy', [RoleController::class, 'destroy'])->middleware('permission:roles.manage'); // DONE: Delete role (RolesPage)
            Route::post('/{id}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.manage');
        });

        // Permissions (literal routes MUST come before parameterized {id})
        Route::group(['prefix' => 'permissions'], function () {
            Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permissions.view'); // DONE: Permission list (PermissionsPage)
            Route::post('/store', [PermissionController::class, 'store'])->middleware('permission:permissions.manage'); // DONE: Permission create (PermissionsPage)
            Route::get('/groups', [PermissionController::class, 'groups'])->middleware('permission:permissions.view'); // DONE: Permission groups list (PermissionsPage)
            Route::get('/grouped', [PermissionController::class, 'grouped'])->middleware('permission:permissions.view'); // DONE: Grouped permissions matrix (RoleFormPage, RoleDetailPage, AdminDetailPage)
            Route::get('/{id}', [PermissionController::class, 'show'])->middleware('permission:permissions.view'); // DONE: Get permission with associated roles (PermissionsPage)
            Route::put('/{id}/update', [PermissionController::class, 'update'])->middleware('permission:permissions.manage'); // DONE: Update permission (PermissionsPage)
            Route::delete('/{id}/destroy', [PermissionController::class, 'destroy'])->middleware('permission:permissions.manage'); // DONE: Delete permission (PermissionsPage)
        });

        // Admin / Staff Accounts Management
        Route::group(['prefix' => 'admins'], function () {
            Route::get('/', [AdminStaffController::class, 'index'])->middleware('permission:users.view'); // DONE: Admin staff list (AdminsPage)
            Route::get('stats', [AdminStaffController::class, 'stats'])->middleware('permission:users.view'); // DONE: Admin role stats (AdminsPage)
            Route::post('/', [AdminStaffController::class, 'store'])->middleware('permission:roles.manage'); // DONE: Create admin staff (AdminsPage)
            Route::get('{admin}', [AdminStaffController::class, 'show'])->middleware('permission:users.view'); // DONE: Admin profile, permissions & activity (AdminDetailPage)
            Route::put('{admin}', [AdminStaffController::class, 'update'])->middleware('permission:roles.manage'); // DONE: Update admin staff (AdminsPage)
            Route::patch('{admin}/status', [AdminStaffController::class, 'updateStatus'])->middleware('permission:users.ban'); // DONE: Update admin status (AdminsPage, AdminDetailPage)
            Route::delete('{admin}', [AdminStaffController::class, 'destroy'])->middleware('permission:users.delete'); // DONE: Delete admin staff (AdminsPage)
        });

        // User-Role Assignments
        Route::get('users', [UserRoleController::class, 'index'])->middleware('permission:users.view');
        Route::post('users/assign', [UserRoleController::class, 'assign'])->middleware('permission:roles.manage'); // DONE: Assign/sync roles to user (AdminDetailPage)
        Route::get('users/{userId}/permissions', [UserRoleController::class, 'userPermissions'])->middleware('permission:users.view');
    });
});

// Authenticated user route (outside admin prefix)
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'banned'])
    ->get('auth/my-permissions', [UserRoleController::class, 'myRoles']); // DONE: Fetch current admin permissions (auth store)
