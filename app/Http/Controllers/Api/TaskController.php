<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    private const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'Assigned',
        3 => 'In Progress',
        4 => 'Completed',
    ];

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

        $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_critical_path' => 'nullable|boolean',
            'deadline' => 'nullable|date',
        ]);

        try {
            $task = Task::create([
                'project_id' => $project->id,
                'title' => $request->title,
                'description' => $request->description,
                'is_critical_path' => $request->boolean('is_critical_path'),
                'deadline' => $request->deadline,
                'status' => 1,
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Task created successfully.',
                'data' => $this->detailResource($task),
            ], 201);

        } catch (\Exception $e) {
            Log::error('API create task: '.$e->getMessage());

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

            return response()->json([
                'message' => 'Supervisor assigned successfully.',
                'data' => $this->detailResource($task->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('API assign supervisor: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

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
            'project_id' => $task->project_id,
            'title' => $task->title,
            'is_critical_path' => $task->is_critical_path,
            'deadline' => $task->deadline?->format('Y-m-d'),
            'status' => $task->status,
            'status_label' => self::STATUS_LABELS[$task->status] ?? 'Unknown',
            'assigned_to' => $task->assignee ? [
                'id' => $task->assignee->id,
                'full_name' => $task->assignee->full_name,
            ] : null,
        ];
    }

    private function detailResource(Task $task): array
    {
        $task->loadMissing('project', 'assignee', 'creator');

        return [
            'id' => $task->id,
            'project' => [
                'id' => $task->project->id,
                'name' => $task->project->name,
            ],
            'title' => $task->title,
            'description' => $task->description,
            'is_critical_path' => $task->is_critical_path,
            'deadline' => $task->deadline?->format('Y-m-d'),
            'status' => $task->status,
            'status_label' => self::STATUS_LABELS[$task->status] ?? 'Unknown',
            'assigned_to' => $task->assignee ? [
                'id' => $task->assignee->id,
                'full_name' => $task->assignee->full_name,
                'email' => $task->assignee->email,
            ] : null,
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
