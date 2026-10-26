<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1')->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/settings', [PageController::class, 'settings'])->name('settings');

    Route::get('/projects', [PageController::class, 'projects'])->middleware('permission:projects.view')->name('projects.index');
    Route::get('/projects/{project}', [PageController::class, 'project'])->whereNumber('project')->middleware('permission:projects.view')->name('projects.show');
    Route::get('/credentials', [PageController::class, 'credentials'])->middleware('permission:credentials.view')->name('credentials.index');
    Route::get('/users', [PageController::class, 'users'])->middleware('permission:users.manage')->name('users.index');
    Route::get('/roles', [PageController::class, 'roles'])->middleware('permission:roles.manage')->name('roles.index');
    Route::get('/profiles', [PageController::class, 'profiles'])->middleware('permission:profiles.manage')->name('profiles.index');
    Route::get('/audit', [PageController::class, 'audit'])->middleware('permission:audit.view')->name('audit.index');
});
