<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CredentialController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
| API interna. Se sirve bajo /api con el grupo `web` (sesión + CSRF) y requiere
| usuario autenticado y activo. Cada endpoint exige además su permiso.
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1');
    Route::get('/meta', MetaController::class);

    Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->middleware('permission:dashboard.view');

    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project');
    });
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:projects.create');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project')->middleware('permission:projects.update');
    Route::patch('/projects/{project}/active', [ProjectController::class, 'setActive'])->whereNumber('project')->middleware('permission:projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->whereNumber('project')->middleware('permission:projects.delete');

    Route::middleware('permission:credentials.view')->group(function () {
        Route::get('/credentials', [CredentialController::class, 'index']);
        Route::get('/credentials/export', [CredentialController::class, 'export'])->middleware('permission:credentials.export');
        Route::get('/credentials/{credential}', [CredentialController::class, 'show'])->whereNumber('credential');
    });
    Route::post('/credentials', [CredentialController::class, 'store'])->middleware('permission:credentials.create');
    Route::put('/credentials/{credential}', [CredentialController::class, 'update'])->whereNumber('credential')->middleware('permission:credentials.update');
    Route::delete('/credentials/{credential}', [CredentialController::class, 'destroy'])->whereNumber('credential')->middleware('permission:credentials.delete');
    Route::post('/credentials/{credential}/reveal', [CredentialController::class, 'reveal'])->whereNumber('credential')
        ->middleware(['permission:credentials.reveal|credentials.copy', 'throttle:30,1']);

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // Lectura de catálogos de acceso: la necesitan los formularios de usuarios.
    Route::middleware('permission:roles.manage|users.manage|profiles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/profiles', [ProfileController::class, 'index']);
        Route::get('/permissions', [PermissionController::class, 'index']);
    });
    Route::middleware('permission:roles.manage')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    });
    Route::middleware('permission:profiles.manage')->group(function () {
        Route::post('/profiles', [ProfileController::class, 'store']);
        Route::put('/profiles/{profile}', [ProfileController::class, 'update']);
        Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy']);
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
});
