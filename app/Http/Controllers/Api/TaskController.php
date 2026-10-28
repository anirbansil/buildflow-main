<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    private const ATTACHMENT_RULE = 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240';

    private const MAX_ATTACHMENTS = 10;

    /**
     * List tasks for a project.
     * The authenticated user must be assigned to the project.
     *
     * GET /api/v1/projects/{project}/tasks
     */
    public function index(Request $request, int $project): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $project);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $tasks = $project->tasks()
            ->with('assignee')
            ->orderBy('deadline', 'asc')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $tasks->map(fn ($task) => $this->listResource($task)),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
            ],
        ]);
    }

    /**
     * Create a new task under a project.
     *
     * POST /api/v1/projects/{project}/tasks
     */
    public function store(Request $request, int $project): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $project);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole(['OWNER', 'SUPERVISOR'])) {
            return response()->json(['message' => 'Only an owner or supervisor can create tasks.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'safety_note' => 'nullable|string',
            'is_critical_path' => 'nullable|boolean',
            'priority' => 'nullable|string|in:'.implode(',', array_keys(Task::PRIORITIES)),
            'phase_label' => 'nullable|string|max:100',
            'deadline' => 'nullable|date',
            'deadline_time' => 'nullable|date_format:H:i',
            'worker_id' => 'nullable|integer|exists:users,id',
            'attachments' => 'nullable|array|max:'.self::MAX_ATTACHMENTS,
            'attachments.*' => self::ATTACHMENT_RULE,
            'attachment_category' => 'nullable|string|max:100',
        ], $this->uploadMessages());

        $worker = $request->filled('worker_id') ? User::find($request->worker_id) : null;

        if ($worker && ($error = $this->workerAssignmentError($project, $worker))) {
            return response()->json(['message' => $error], 422);
        }

        try {
            $task = Task::create([
                'project_id' => $project->id,
                'title' => $request->title,
                'description' => $request->description,
                'safety_note' => $request->safety_note,
                'is_critical_path' => $request->boolean('is_critical_path'),
                'priority' => Task::PRIORITIES[$request->input('priority', 'medium')],
                'phase_label' => $request->phase_label,
                'deadline' => $request->deadline,
                'deadline_time' => $request->deadline_time,
                'status' => 1,
                'created_by' => $request->user()->id,
            ]);

            ActivityLog::record($project->id, 'task_created', 'Task "'.$task->title.'" created', $request->user()->id);

            if ($worker) {
                $this->assignWorkerToTask($task, $worker, $request->user());
            }

            if ($request->hasFile('attachments')) {
                $this->storeAttachments($task, $request->file('attachments'), $request->attachment_category, $request->user()->id);
            }

            return response()->json([
                'message' => 'Task created successfully.',
                'data' => $this->detailResource($task->fresh()),
            ], 201);

        } catch (\Throwable $e) {
            Log::error('API create task: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Edit a task. Caller must be an OWNER or SUPERVISOR on the task's project. Send only
     * the fields to change. `worker_id` assigns or reassigns the worker; send it as null
     * to unassign. New files in `attachments[]` are added to the existing ones.
     *
     * POST /api/v1/tasks/{id}/update
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole(['OWNER', 'SUPERVISOR'])) {
            return response()->json(['message' => 'Only an owner or supervisor can edit tasks.'], 403);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'safety_note' => 'nullable|string',
            'is_critical_path' => 'nullable|boolean',
            'priority' => 'nullable|string|in:'.implode(',', array_keys(Task::PRIORITIES)),
            'phase_label' => 'nullable|string|max:100',
            'deadline' => 'nullable|date',
            'deadline_time' => 'nullable|date_format:H:i',
            'worker_id' => 'nullable|integer|exists:users,id',
            'attachments' => 'nullable|array|max:'.self::MAX_ATTACHMENTS,
            'attachments.*' => self::ATTACHMENT_RULE,
            'attachment_category' => 'nullable|string|max:100',
        ], $this->uploadMessages());

        $worker = $request->filled('worker_id') ? User::find($request->worker_id) : null;

        if ($worker && ($error = $this->workerAssignmentError($task->project, $worker))) {
            return response()->json(['message' => $error], 422);
        }

        try {
            $fields = $request->only(['title', 'description', 'safety_note', 'phase_label', 'deadline', 'deadline_time']);

            if ($request->has('is_critical_path')) {
                $fields['is_critical_path'] = $request->boolean('is_critical_path');
            }

            if ($request->filled('priority')) {
                $fields['priority'] = Task::PRIORITIES[$request->priority];
            }

            $task->update($fields);

            if ($task->wasChanged()) {
                ActivityLog::record($task->project_id, 'task_updated', 'Task "'.$task->title.'" updated', $request->user()->id);
            }

            if ($worker && $worker->id !== $task->assigned_worker_id) {
                $this->assignWorkerToTask($task, $worker, $request->user());
            } elseif ($request->has('worker_id') && ! $request->filled('worker_id') && $task->assigned_worker_id) {
                $task->update(['assigned_worker_id' => null, 'worker_assigned_by' => null, 'worker_assigned_at' => null]);

                ActivityLog::record($task->project_id, 'task_worker_unassigned', 'Worker removed from task "'.$task->title.'"', $request->user()->id);
            }

            if ($request->hasFile('attachments')) {
                $this->storeAttachments($task, $request->file('attachments'), $request->attachment_category, $request->user()->id);
            }

            return response()->json([
                'message' => 'Task updated successfully.',
                'data' => $this->detailResource($task->fresh()),
            ]);

        } catch (\Throwable $e) {
            Log::error('API update task: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Delete a task and its attachment files. Caller must be an OWNER or SUPERVISOR on the
     * task's project. A task that already has work submissions can't be deleted, so the
     * review and earnings history isn't lost.
     *
     * DELETE /api/v1/tasks/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole(['OWNER', 'SUPERVISOR'])) {
            return response()->json(['message' => 'Only an owner or supervisor can delete tasks.'], 403);
        }

        if ($task->submissions()->exists()) {
            return response()->json(['message' => 'This task already has work submissions and can\'t be deleted.'], 422);
        }

        try {
            $paths = $task->attachments()->pluck('file_path')->all();

            $task->delete();

            Storage::disk('public')->delete($paths);

            ActivityLog::record($task->project_id, 'task_deleted', 'Task "'.$task->title.'" deleted', $request->user()->id);

            return response()->json(['message' => 'Task deleted successfully.']);

        } catch (\Throwable $e) {
            Log::error('API delete task: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * View a single task's detail.
     *
     * GET /api/v1/tasks/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        return response()->json(['data' => $this->detailResource($task)]);
    }

    /**
     * List supervisors available to assign to a task, with workload and performance.
     *
     * GET /api/v1/tasks/{id}/available-supervisors
     */
    public function availableSupervisors(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        $supervisors = User::query()
            ->role('SUPERVISOR')
            ->where('status', 1)
            ->withCount(['tasksAssigned as active_task_count' => fn ($q) => $q->whereIn('status', [2, 3]),
            ])
            ->orderByDesc('performance_score')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $supervisors->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'title' => $user->title,
                'profile_photo' => $user->profile_photo ? url('storage/'.$user->profile_photo) : null,
                'experience_years' => $user->experience_years,
                'performance_score' => $user->performance_score,
                'current_workload' => $user->active_task_count,
            ]),
            'meta' => [
                'current_page' => $supervisors->currentPage(),
                'last_page' => $supervisors->lastPage(),
                'per_page' => $supervisors->perPage(),
                'total' => $supervisors->total(),
                'from' => $supervisors->firstItem(),
                'to' => $supervisors->lastItem(),
            ],
        ]);
    }

    /**
     * Assign a supervisor to a task.
     *
     * POST /api/v1/tasks/{id}/assign-supervisor
     */
    public function assignSupervisor(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        $request->validate([
            'supervisor_id' => 'required|integer|exists:users,id',
        ]);

        $supervisor = User::find($request->supervisor_id);

        if (! $supervisor || ! $supervisor->hasRole('SUPERVISOR')) {
            return response()->json(['message' => 'Selected user is not a supervisor.'], 422);
        }

        try {
            $task->update([
                'assigned_to' => $supervisor->id,
                'status' => 2,
                'assigned_at' => now(),
            ]);

            ActivityLog::record(
                $task->project_id,
                'task_assigned',
                $supervisor->full_name.' assigned to task "'.$task->title.'"',
                $request->user()->id
            );

            return response()->json([
                'message' => 'Supervisor assigned successfully.',
                'data' => $this->detailResource($task->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('API assign supervisor: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Assign a task directly to a worker. Caller must be an OWNER or SUPERVISOR, and the
     * worker must already be assigned to the task's project.
     *
     * POST /api/v1/tasks/{id}/assign-worker
     */
    public function assignWorker(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole(['OWNER', 'SUPERVISOR'])) {
            return response()->json(['message' => 'Only an owner or supervisor can assign a worker.'], 403);
        }

        $request->validate([
            'worker_id' => 'required|integer|exists:users,id',
        ]);

        $worker = User::find($request->worker_id);

        if ($error = $this->workerAssignmentError($task->project, $worker)) {
            return response()->json(['message' => $error], 422);
        }

        try {
            $this->assignWorkerToTask($task, $worker, $request->user());

            return response()->json([
                'message' => 'Worker assigned successfully.',
                'data' => $this->detailResource($task->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('API assign worker: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * The authenticated worker's own task list, across every project. Search matches
     * title, task_code or the project's location.
     *
     * GET /api/v1/my-tasks
     */
    public function myTasks(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|integer|between:1,6',
            'search' => 'nullable|string|max:255',
        ]);

        $tasks = Task::where('assigned_worker_id', $request->user()->id)
            ->with(['project', 'workerAssignedBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->integer('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.trim($request->search).'%';
                $q->where(fn ($q2) => $q2->where('title', 'like', $term)
                    ->orWhere('task_code', 'like', $term)
                    ->orWhereHas('project', fn ($p) => $p->where('location', 'like', $term)));
            })
            ->orderBy('deadline', 'asc')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $tasks->map(fn ($task) => $this->myTaskResource($task)),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
            ],
        ]);
    }

    /**
     * Attach files (blueprint, reference photo, etc.) to a task. Caller must be an
     * OWNER or SUPERVISOR. Send as multipart/form-data, either one file as `file`
     * (returns one object) or several as `files[]` (returns an array).
     *
     * POST /api/v1/tasks/{id}/attachments
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole(['OWNER', 'SUPERVISOR'])) {
            return response()->json(['message' => 'Only an owner or supervisor can add attachments.'], 403);
        }

        $request->validate([
            'file' => 'required_without:files|'.self::ATTACHMENT_RULE,
            'files' => 'required_without:file|array|max:'.self::MAX_ATTACHMENTS,
            'files.*' => self::ATTACHMENT_RULE,
            'category' => 'nullable|string|max:100',
        ], $this->uploadMessages());

        $single = $request->hasFile('file');
        $files = $single ? [$request->file('file')] : $request->file('files');

        try {
            $attachments = $this->storeAttachments($task, $files, $request->category, $request->user()->id);

            return response()->json([
                'message' => count($attachments) > 1 ? 'Attachments uploaded successfully.' : 'Attachment uploaded successfully.',
                'data' => $single
                    ? $this->attachmentResource($attachments[0])
                    : array_map(fn ($a) => $this->attachmentResource($a), $attachments),
            ], 201);

        } catch (\Throwable $e) {
            Log::error('API upload task attachment: '.$e->getMessage());

            return response()->json(['message' => 'Server error. The file could not be saved.'], 500);
        }
    }

    /**
     * DELETE /api/v1/tasks/{id}/attachments/{attachmentId}
     */
    public function deleteAttachment(Request $request, int $id, int $attachmentId): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        if (! $request->user()->hasRole(['OWNER', 'SUPERVISOR'])) {
            return response()->json(['message' => 'Only an owner or supervisor can delete attachments.'], 403);
        }

        $attachment = $task->attachments()->find($attachmentId);

        if (! $attachment) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully.']);
    }

    /**
     * Worker starts their assigned task (Pending/Assigned → In Progress). Caller must be
     * the task's assigned_worker_id.
     *
     * POST /api/v1/tasks/{id}/start
     */
    public function startTask(Request $request, int $id): JsonResponse
    {
        $task = $this->findAccessibleTask($request, $id);

        if (! $task) {
            return response()->json(['message' => 'Task not found or you do not have access.'], 404);
        }

        if ($task->assigned_worker_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the assigned worker can start this task.'], 403);
        }

        if (! in_array($task->status, [1, 2], true)) {
            return response()->json(['message' => 'Only a pending or assigned task can be started.'], 422);
        }

        $task->update(['status' => 3]);

        ActivityLog::record($task->project_id, 'task_started', $request->user()->full_name.' started task "'.$task->title.'"', $request->user()->id);

        return response()->json([
            'message' => 'Task started successfully.',
            'data' => $this->detailResource($task->fresh()),
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Why this user can't be given a task on the project, or null when they can.
     */
    private function workerAssignmentError(Project $project, User $worker): ?string
    {
        if (! $worker->hasRole('WORKER')) {
            return 'Selected user is not a worker.';
        }

        if (! $project->users()->where('users.id', $worker->id)->exists()) {
            return 'This worker is not assigned to the task\'s project.';
        }

        return null;
    }

    private function assignWorkerToTask(Task $task, User $worker, User $assignedBy): void
    {
        $task->update([
            'assigned_worker_id' => $worker->id,
            'worker_assigned_by' => $assignedBy->id,
            'worker_assigned_at' => now(),
        ]);

        ActivityLog::record(
            $task->project_id,
            'task_worker_assigned',
            $worker->full_name.' assigned to task "'.$task->title.'"',
            $assignedBy->id
        );

        UserNotification::notifyUser(
            $worker->id,
            $task->project_id,
            'task',
            'New Task Assigned',
            'You have been assigned to "'.$task->title.'". Please review the details.',
            "/tasks/{$task->id}"
        );
    }

    /**
     * @param  \Illuminate\Http\UploadedFile[]  $files
     * @return TaskAttachment[]
     */
    private function storeAttachments(Task $task, array $files, ?string $category, int $userId): array
    {
        $attachments = [];

        foreach ($files as $file) {
            $path = $file->store("task_attachments/{$task->id}", 'public');

            if (! $path) {
                throw new \RuntimeException("Could not write task attachment for task {$task->id}");
            }

            $attachments[] = $task->attachments()->create([
                'uploaded_by' => $userId,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'category' => $category,
                'file_size' => $file->getSize(),
            ]);
        }

        return $attachments;
    }

    private function attachmentResource(TaskAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'file_name' => $attachment->file_name,
            'category' => $attachment->category,
            'file_size' => $attachment->file_size,
            'url' => url('storage/'.$attachment->file_path),
        ];
    }

    /**
     * A file over PHP's upload_max_filesize never reaches Laravel, which only reports
     * "failed to upload", so say what the real limit is.
     */
    private function uploadMessages(): array
    {
        return [
            'uploaded' => 'The :attribute failed to upload. It is probably larger than the server limit of '.ini_get('upload_max_filesize').'.',
        ];
    }

    private function myTaskResource(Task $task): array
    {
        return [
            'id' => $task->id,
            'task_code' => $task->task_code,
            'title' => $task->title,
            'project' => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null,
            'priority' => array_search($task->priority, Task::PRIORITIES, true) ?: null,
            'priority_label' => Task::PRIORITY_LABELS[$task->priority] ?? null,
            'phase_label' => $task->phase_label,
            'status' => $task->status,
            'status_label' => Task::STATUS_LABELS[$task->status] ?? 'Unknown',
            'deadline' => $task->deadline?->format('Y-m-d'),
            'deadline_time' => $task->deadline_time,
            'assigned_by' => $task->workerAssignedBy?->full_name,
        ];
    }

    private function findAccessibleProject(Request $request, int $projectId): ?Project
    {
        return $request->user()
            ->projects()
            ->where('projects.id', $projectId)
            ->first();
    }

    private function findAccessibleTask(Request $request, int $taskId): ?Task
    {
        $task = Task::with(['project', 'assignee', 'creator'])->find($taskId);

        if (! $task) {
            return null;
        }

        $hasAccess = $request->user()
            ->projects()
            ->where('projects.id', $task->project_id)
            ->exists();

        return $hasAccess ? $task : null;
    }

    private function listResource(Task $task): array
    {
        return [
            'id' => $task->id,
            'task_code' => $task->task_code,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'is_critical_path' => $task->is_critical_path,
            'priority' => array_search($task->priority, Task::PRIORITIES, true) ?: null,
            'priority_label' => Task::PRIORITY_LABELS[$task->priority] ?? null,
            'phase_label' => $task->phase_label,
            'deadline' => $task->deadline?->format('Y-m-d'),
            'deadline_time' => $task->deadline_time,
            'status' => $task->status,
            'status_label' => Task::STATUS_LABELS[$task->status] ?? 'Unknown',
            'assigned_to' => $task->assignee ? [
                'id' => $task->assignee->id,
                'full_name' => $task->assignee->full_name,
            ] : null,
            'assigned_worker' => $task->worker ? [
                'id' => $task->worker->id,
                'full_name' => $task->worker->full_name,
            ] : null,
        ];
    }

    private function detailResource(Task $task): array
    {
        $task->loadMissing('project', 'assignee', 'creator', 'worker', 'workerAssignedBy', 'attachments');

        return [
            'id' => $task->id,
            'task_code' => $task->task_code,
            'project' => [
                'id' => $task->project->id,
                'name' => $task->project->name,
            ],
            'title' => $task->title,
            'description' => $task->description,
            'safety_note' => $task->safety_note,
            'is_critical_path' => $task->is_critical_path,
            'priority' => array_search($task->priority, Task::PRIORITIES, true) ?: null,
            'priority_label' => Task::PRIORITY_LABELS[$task->priority] ?? null,
            'phase_label' => $task->phase_label,
            'deadline' => $task->deadline?->format('Y-m-d'),
            'deadline_time' => $task->deadline_time,
            'status' => $task->status,
            'status_label' => Task::STATUS_LABELS[$task->status] ?? 'Unknown',
            'assigned_to' => $task->assignee ? [
                'id' => $task->assignee->id,
                'full_name' => $task->assignee->full_name,
                'email' => $task->assignee->email,
                'profile_photo' => $task->assignee->profile_photo ? url('storage/'.$task->assignee->profile_photo) : null,
            ] : null,
            'assigned_worker' => $task->worker ? [
                'id' => $task->worker->id,
                'full_name' => $task->worker->full_name,
                'email' => $task->worker->email,
            ] : null,
            'worker_assigned_by' => $task->workerAssignedBy?->full_name,
            'worker_assigned_at' => $task->worker_assigned_at?->toDateTimeString(),
            'attachments' => $task->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'category' => $a->category,
                'file_size' => $a->file_size,
                'url' => url('storage/'.$a->file_path),
            ]),
            'assigned_at' => $task->assigned_at?->toDateTimeString(),
            'created_by' => $task->creator ? [
                'id' => $task->creator->id,
                'full_name' => $task->creator->full_name,
            ] : null,
            'created_at' => $task->created_at?->toDateTimeString(),
            'updated_at' => $task->updated_at?->toDateTimeString(),
        ];
    }
}
