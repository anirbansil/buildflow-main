<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EarningController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ComplianceDocumentController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me',              [AuthController::class, 'me']);
        Route::get('/dashboard',       [DashboardController::class, 'index']);
        Route::get('/worker-dashboard', [DashboardController::class, 'worker']);
        Route::post('/logout',         [AuthController::class, 'logout']);
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/{id}/worker-profile', [UserController::class, 'updateWorkerProfile']);

        // Project
        Route::get('/projects',                  [ProjectController::class, 'index']);
        Route::get('/projects/{id}',             [ProjectController::class, 'show']);
        Route::middleware('owner')->group(function () {
            Route::post('/projects',                 [ProjectController::class, 'store']);
            Route::post('/projects/{id}/update',        [ProjectController::class, 'update']);
            Route::post('/projects/{id}/assign',        [ProjectController::class, 'assign']);
            Route::post('/projects/{id}/toggle-status', [ProjectController::class, 'toggleStatus']);
            Route::delete('/projects/{id}/images/{imageId}', [ProjectController::class, 'deleteImage']);
        });

        // Tasks
        Route::get('/projects/{project}/tasks',  [TaskController::class, 'index']);
        Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);
        Route::get('/tasks/{id}',                          [TaskController::class, 'show']);
        Route::post('/tasks/{id}/update',                  [TaskController::class, 'update']);
        Route::delete('/tasks/{id}',                       [TaskController::class, 'destroy']);
        Route::get('/tasks/{id}/available-supervisors',    [TaskController::class, 'availableSupervisors']);
        Route::post('/tasks/{id}/assign-supervisor',       [TaskController::class, 'assignSupervisor']);
        Route::post('/tasks/{id}/assign-worker',           [TaskController::class, 'assignWorker']);
        Route::get('/my-tasks', [TaskController::class, 'myTasks']);
        Route::post('/tasks/{id}/attachments', [TaskController::class, 'uploadAttachment']);
        Route::delete('/tasks/{id}/attachments/{attachmentId}', [TaskController::class, 'deleteAttachment']);
        Route::post('/tasks/{id}/start', [TaskController::class, 'startTask']);

        // Materials
        Route::get('/projects/{project}/materials',  [MaterialController::class, 'index']);
        Route::post('/projects/{project}/materials', [MaterialController::class, 'store']);
        Route::post('/materials/{id}/update',        [MaterialController::class, 'update']);

        // Milestones
        Route::get('/projects/{project}/milestones',  [MilestoneController::class, 'index']);
        Route::post('/projects/{project}/milestones', [MilestoneController::class, 'store']);
        Route::post('/milestones/{id}/update',        [MilestoneController::class, 'update']);

        // Activity
        Route::get('/projects/{project}/activity', [ActivityController::class, 'index']);

        // Submissions
        Route::get('/submissions',           [SubmissionController::class, 'index']);
        Route::get('/submissions/{id}',      [SubmissionController::class, 'show']);
        Route::post('/tasks/{task}/submissions',            [SubmissionController::class, 'store']);
        Route::post('/submissions/{id}/start-review',         [SubmissionController::class, 'startReview']);
        Route::post('/submissions/{id}/verify',              [SubmissionController::class, 'verify']);
        Route::post('/submissions/{id}/return-to-worker',    [SubmissionController::class, 'returnToWorker']);
        Route::post('/submissions/{id}/approve',             [SubmissionController::class, 'approve']);
        Route::post('/submissions/{id}/reject',               [SubmissionController::class, 'reject']);
        Route::post('/submissions/{id}/request-revision',    [SubmissionController::class, 'requestRevision']);

        // Payments
        Route::get('/payments',                            [PaymentController::class, 'index']);
        Route::get('/projects/{project}/payments',         [PaymentController::class, 'indexForProject']);
        Route::post('/projects/{project}/payments',        [PaymentController::class, 'store']);
        Route::get('/projects/{project}/financial-summary', [PaymentController::class, 'financialSummary']);
        Route::post('/payments/{id}/approve', [PaymentController::class, 'approve']);
        Route::post('/payments/{id}/clear',   [PaymentController::class, 'clear']);
        Route::post('/payments/{id}/reject',  [PaymentController::class, 'reject']);
        Route::get('/payments/export',        [PaymentController::class, 'export']);

        // Reports
        Route::get('/projects/{project}/reports/project', [ReportController::class, 'project']);
        Route::get('/reports/budget',                      [ReportController::class, 'budget']);

        // Earnings
        Route::get('/my-earnings', [EarningController::class, 'myEarnings']);
        Route::post('/my-earnings/withdraw', [EarningController::class, 'withdraw']);
        Route::get('/projects/{project}/earnings', [EarningController::class, 'indexForProject']);
        Route::post('/projects/{project}/earnings', [EarningController::class, 'store']);
        Route::post('/earnings/{id}/approve', [EarningController::class, 'approve']);
        Route::post('/earnings/{id}/pay', [EarningController::class, 'pay']);

        // Notifications
        Route::get('/notifications',               [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count',  [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all',     [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read',    [NotificationController::class, 'markRead']);

        // Settings
        Route::get('/settings',            [SettingsController::class, 'show']);
        Route::post('/settings',           [SettingsController::class, 'update']);
        Route::post('/settings/password',  [SettingsController::class, 'changePassword']);

        // Owner profile: company + compliance documents
        Route::get('/company',  [CompanyController::class, 'show']);
        Route::post('/company', [CompanyController::class, 'save']);
        Route::get('/compliance-documents',                [ComplianceDocumentController::class, 'index']);
        Route::post('/compliance-documents',               [ComplianceDocumentController::class, 'store']);
        Route::get('/compliance-documents/{id}/download',  [ComplianceDocumentController::class, 'download']);
    });
});
