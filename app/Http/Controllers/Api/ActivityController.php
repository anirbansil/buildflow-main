<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Recent activity feed of a project (Project Detail → Recent Activity / Activity tab).
     * The authenticated user must be assigned to the project.
     *
     * GET /api/v1/projects/{project}/activity
     */
    public function index(Request $request, int $project): JsonResponse
    {
        $project = $request->user()
            ->projects()
            ->where('projects.id', $project)
            ->first();

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $logs = $project->activityLogs()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'project_id' => $log->project_id,
                'type' => $log->type,
                'description' => $log->description,
                'actor' => $log->user ? [
                    'id' => $log->user->id,
                    'full_name' => $log->user->full_name,
                ] : null,
                'created_at' => $log->created_at?->toDateTimeString(),
                'time_ago' => $log->created_at?->diffForHumans(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ]);
    }
}
