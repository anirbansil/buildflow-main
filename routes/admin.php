<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProjectController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'superadmin'])
    ->group(function () {

        // User management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::post('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/users/{user}/delete', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/users/{user}/change-status', [UserController::class, 'changeStatus'])->name('users.changeStatus');

        // Project management
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::post('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::get('/projects/{project}/delete', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::get('/projects/{project}/change-status', [ProjectController::class, 'changeStatus'])->name('projects.changeStatus');
    });
