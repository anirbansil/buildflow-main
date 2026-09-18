<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me',              [AuthController::class, 'me']);
        Route::post('/logout',         [AuthController::class, 'logout']);
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);

        // Users
        Route::get('/users', [UserController::class, 'index']);

        // Project
        Route::get('/projects',                  [ProjectController::class, 'index']);
        Route::get('/projects/{id}',             [ProjectController::class, 'show']);
        Route::post('/projects',                 [ProjectController::class, 'store']);
        Route::post('/projects/{id}/update',        [ProjectController::class, 'update']);
        Route::post('/projects/{id}/assign',        [ProjectController::class, 'assign']);
        Route::post('/projects/{id}/toggle-status', [ProjectController::class, 'toggleStatus']);
        Route::delete('/projects/{id}/images/{imageId}', [ProjectController::class, 'deleteImage']);

        // Tasks
        Route::get('/projects/{project}/tasks',  [TaskController::class, 'index']);
        Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);
        Route::get('/tasks/{id}',                          [TaskController::class, 'show']);
        Route::get('/tasks/{id}/available-supervisors',    [TaskController::class, 'availableSupervisors']);
        Route::post('/tasks/{id}/assign-supervisor',       [TaskController::class, 'assignSupervisor']);
    });
});
