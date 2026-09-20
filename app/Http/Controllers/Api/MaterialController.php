<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MaterialController extends Controller
{
    /**
     * List materials of a project (Material Allocation table).
     * The authenticated user must be assigned to the project.
     *
     * GET /api/v1/projects/{project}/materials
     */
    public function index(Request $request, int $project): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $project);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $materials = $project->materials()
            ->orderBy('id')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $materials->map(fn ($material) => $this->resource($material)),
            'meta' => [
                'current_page' => $materials->currentPage(),
                'last_page' => $materials->lastPage(),
                'per_page' => $materials->perPage(),
                'total' => $materials->total(),
                'from' => $materials->firstItem(),
                'to' => $materials->lastItem(),
            ],
        ]);
    }

    /**
     * Add a material to a project.
     *
     * POST /api/v1/projects/{project}/materials
     */
    public function store(Request $request, int $project): JsonResponse
    {
        $project = $this->findAccessibleProject($request, $project);

        if (! $project) {
            return response()->json(['message' => 'Project not found or you do not have access.'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'unit' => 'required|string|max:50',
            'estimated_qty' => 'required|numeric|min:0',
            'delivered_qty' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:'.implode(',', array_keys(ProjectMaterial::STATUSES)),
        ]);

        try {
            $material = $project->materials()->create([
                'name' => $request->name,
                'unit' => $request->unit,
                'estimated_qty' => $request->estimated_qty,
                'delivered_qty' => $request->input('delivered_qty', 0),
                'status' => ProjectMaterial::STATUSES[$request->input('status', 'on_track')],
            ]);

            ActivityLog::record($project->id, 'material_added', 'Material "'.$material->name.'" added', $request->user()->id);

            return response()->json([
                'message' => 'Material added successfully.',
                'data' => $this->resource($material),
            ], 201);

        } catch (\Exception $e) {
            Log::error('API create material: '.$e->getMessage());

            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Update a material (only the fields sent are changed).
     *
     * POST /api/v1/materials/{id}/update
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $material = ProjectMaterial::find($id);

        $hasAccess = $material && $this->findAccessibleProject($request, $material->project_id);

        if (! $hasAccess) {
            return response()->json(['message' => 'Material not found or you do not have access.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'unit' => 'sometimes|required|string|max:50',
            'estimated_qty' => 'sometimes|required|numeric|min:0',
            'delivered_qty' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|string|in:'.implode(',', array_keys(ProjectMaterial::STATUSES)),
        ]);

        try {
            $data = $request->only(['name', 'unit', 'estimated_qty', 'delivered_qty']);
            $statusChangedTo = null;

            if ($request->filled('status')) {
                $data['status'] = ProjectMaterial::STATUSES[$request->status];

                if ($data['status'] !== $material->status) {
                    $statusChangedTo = $request->status;
                }
            }

            $material->update($data);

            if ($statusChangedTo === 'delivered') {
                ActivityLog::record($material->project_id, 'material_delivered', 'Material "'.$material->name.'" delivered', $request->user()->id);
            } elseif ($statusChangedTo === 'delayed') {
                ActivityLog::record($material->project_id, 'material_delayed', 'Material "'.$material->name.'" marked delayed', $request->user()->id);
            }

            return response()->json([
                'message' => 'Material updated successfully.',
                'data' => $this->resource($material->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('API update material: '.$e->getMessage());

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

    private function resource(ProjectMaterial $material): array
    {
        return [
            'id' => $material->id,
            'project_id' => $material->project_id,
            'name' => $material->name,
            'unit' => $material->unit,
            'estimated_qty' => $material->estimated_qty,
            'delivered_qty' => $material->delivered_qty,
            'status' => array_search($material->status, ProjectMaterial::STATUSES, true) ?: null,
            'status_label' => ProjectMaterial::STATUS_LABELS[$material->status] ?? null,
            'created_at' => $material->created_at?->toDateTimeString(),
            'updated_at' => $material->updated_at?->toDateTimeString(),
        ];
    }
}
