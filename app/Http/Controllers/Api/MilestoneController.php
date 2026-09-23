<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Milestone;
use App\Models\UserNotification;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MilestoneController extends Controller
{
    /**
     * List milestones of a project (Milestones tab).
     * The authenticated user must be assigned to the project.
     *
     * GET /api/v1/projects/{project}/milestones
     */
    public function index(Request $request, int $project): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $project);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $request->validate([
            'status' => 'nullable|string|in:'.implode(',', array_keys(Milestone::STATUSES)),
        ]);

        $milestones = $project->milestones()
            ->when($request->filled('status'), fn ($q) => $q->where('status', Milestone::STATUSES[$request->status]))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $milestones->map(fn ($milestone) => $this->resource($milestone)),
            'meta' => [
                'current_page' => $milestones->currentPage(),
                'last_page' => $milestones->lastPage(),
                'per_page' => $milestones->perPage(),
                'total' => $milestones->total(),
                'from' => $milestones->firstItem(),
                'to' => $milestones->lastItem(),
            ],
        ]);
    }

    /**
     * Add a milestone to a project.
     *
     * POST /api/v1/projects/{project}/milestones
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
            'due_date' => 'nullable|date',
            'status' => 'nullable|string|in:'.implode(',', array_keys(Milestone::STATUSES)),
        ]);

        try {
            $status = $request->input('status', 'pending');

            $milestone = $project->milestones()->create([
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
                'status' => Milestone::STATUSES[$status],
                'completed_at' => $status === 'completed' ? now() : null,
            ]);

            ActivityLog::record($project->id, 'milestone_added', 'Milestone "'.$milestone->title.'" added', $request->user()->id);

            return response()->json([
                'message' => 'Milestone created successfully.',
                'data' => $this->resource($milestone),
            ], 201);

        } catch (\Exception $e) {
            Log::error('API create milestone: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Update a milestone (only the fields sent are changed).
     * Send status=completed to mark it complete.
     *
     * POST /api/v1/milestones/{id}/update
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $milestone = Milestone::find($id);

        $hasAccess = $milestone && $this->findAccessibleProject($request, $milestone->project_id);

        if (! $hasAccess) {
            return response()->json(['message' => 'Milestone not found or you do not have access.'], 404);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|required|string|in:'.implode(',', array_keys(Milestone::STATUSES)),
        ]);

        try {
            $data = $request->only(['title', 'description', 'due_date']);
            $justCompleted = false;

            if ($request->filled('status')) {
                $data['status'] = Milestone::STATUSES[$request->status];

                if ($request->status !== 'completed') {
                    $data['completed_at'] = null;
                } elseif ($milestone->status !== Milestone::STATUSES['completed']) {
                    $data['completed_at'] = now();
                    $justCompleted = true;
                }
            }

            $milestone->update($data);

            if ($justCompleted) {
                ActivityLog::record($milestone->project_id, 'milestone_completed', 'Milestone "'.$milestone->title.'" completed', $request->user()->id);
                UserNotification::notifyOwners(
                    $milestone->project,
                    'milestone',
                    'Milestone Completed',
                    'Milestone "'.$milestone->title.'" has been completed.',
                );
            }

            return response()->json([
                'message' => 'Milestone updated successfully.',
                'data' => $this->resource($milestone->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('API update milestone: '.$e->getMessage());

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

    private function resource(Milestone $milestone): array
    {
        $isCompleted = $milestone->status === Milestone::STATUSES['completed'];

        return [
            'id' => $milestone->id,
            'project_id' => $milestone->project_id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'due_date' => $milestone->due_date?->format('Y-m-d'),
            'status' => array_search($milestone->status, Milestone::STATUSES, true) ?: null,
            'status_label' => Milestone::STATUS_LABELS[$milestone->status] ?? null,
            'is_overdue' => ! $isCompleted && $milestone->due_date !== null && $milestone->due_date->isPast() && ! $milestone->due_date->isToday(),
            'completed_at' => $milestone->completed_at?->toDateTimeString(),
            'created_at' => $milestone->created_at?->toDateTimeString(),
            'updated_at' => $milestone->updated_at?->toDateTimeString(),
        ];
    }
}
