<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    /**
     * List all projects assigned to the authenticated user.
     *
     * GET /api/v1/projects
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'phase'    => 'nullable|string|in:' . implode(',', array_keys(Project::PHASES)),
            'category' => 'nullable|string|in:' . implode(',', Project::CATEGORIES),
        ]);

        $query = $request->user()
            ->projects()
            ->where('status', 1)
            ->when($request->filled('search'), fn ($q) =>
                $q->where('name', 'like', '%' . trim($request->search) . '%')
            )
            ->when($request->filled('phase'), fn ($q) =>
                $q->where('projects.phase', Project::PHASES[$request->phase])
            )
            ->when($request->filled('category'), fn ($q) =>
                $q->where('projects.category', $request->category)
            )
            ->orderBy('deadline', 'asc')
            ->orderBy('id', 'desc');

        $projects = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $projects->map(fn ($project) => $this->listResource($project)),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page'    => $projects->lastPage(),
                'per_page'     => $projects->perPage(),
                'total'        => $projects->total(),
                'from'         => $projects->firstItem(),
                'to'           => $projects->lastItem(),
            ],
        ]);
    }

    /**
     * View detail of a single project.
     * The authenticated user must be assigned to the project.
     *
     * GET /api/v1/projects/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $project = $request->user()
            ->projects()
            ->where('projects.id', $id)
            ->where('projects.status', 1)
            ->first();

        if (! $project) {
            return response()->json([
                'message' => 'Project not found or you do not have access.',
            ], 404);
        }

        return response()->json([
            'data' => $this->detailResource($project),
        ]);
    }

    /**
     * Create a new project with optional user assignment and document uploads.
     * Send as multipart/form-data when uploading images.
     *
     * POST /api/v1/projects
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'             => 'required|string|max:150',
            'description'      => 'nullable|string',
            'location'         => 'nullable|string|max:255',
            'lat'              => 'nullable|numeric|between:-90,90',
            'long'             => 'nullable|numeric|between:-180,180',
            'total_budget'     => 'nullable|numeric|min:0',
            'used_budget'      => 'nullable|numeric|min:0',
            'retainage_rate'   => 'nullable|numeric|between:0,100',
            'start_date'       => 'nullable|date',
            'deadline'         => 'nullable|date',
            'status'           => 'sometimes|in:1,2',
            'phase'            => 'nullable|string|in:' . implode(',', array_keys(Project::PHASES)),
            'category'         => 'nullable|string|in:' . implode(',', Project::CATEGORIES),
            'progress'         => 'nullable|integer|between:0,100',
            'general_contractor' => 'nullable|string|max:255',
            'lead_architect'   => 'nullable|string|max:255',
            'site_info'        => 'nullable|string',
            'assigned_users'   => 'nullable|array',
            'assigned_users.*' => 'integer|exists:users,id',
            'images'           => 'nullable|array',
            'images.*'         => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx|max:10240',
            'image_names'      => 'nullable|array',
            'image_names.*'    => 'nullable|string|max:255',
        ]);

        try {
            $project = Project::create([
                'name'         => $request->name,
                'description'  => $request->description,
                'location'     => $request->location,
                'lat'          => $request->lat,
                'long'         => $request->long,
                'total_budget' => $request->total_budget,
                'used_budget'  => $request->input('used_budget', 0),
                'retainage_rate' => $request->input('retainage_rate', 10),
                'start_date'   => $request->start_date,
                'deadline'     => $request->deadline,
                'status'       => $request->input('status', 1),
                'phase'        => Project::PHASES[$request->input('phase', 'planning')],
                'category'     => $request->category,
                'progress'     => $request->input('progress', 0),
                'general_contractor' => $request->general_contractor,
                'lead_architect'     => $request->lead_architect,
                'site_info'          => $request->site_info,
            ]);

            if ($request->filled('assigned_users')) {
                $project->users()->sync($request->assigned_users);
            }

            // Handle document/image uploads
            if ($request->hasFile('images')) {
                $this->storeImages($project, $request);
            }

            $project->loadCount('users');

            return response()->json([
                'message' => 'Project created successfully.',
                'data'    => $this->detailResource($project),
            ], 201);

        } catch (\Exception $e) {
            Log::error('API create project: ' . $e->getMessage());
            return response()->json(['message' => 'Server error.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing project with optional image uploads and user reassignment.
     * Send as multipart/form-data when uploading images, otherwise application/json.
     *
     * POST /api/v1/projects/{id}/update
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = Project::find($id);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $request->validate([
            'name'             => 'sometimes|required|string|max:150',
            'description'      => 'nullable|string',
            'location'         => 'nullable|string|max:255',
            'lat'              => 'nullable|numeric|between:-90,90',
            'long'             => 'nullable|numeric|between:-180,180',
            'total_budget'     => 'nullable|numeric|min:0',
            'used_budget'      => 'nullable|numeric|min:0',
            'retainage_rate'   => 'nullable|numeric|between:0,100',
            'start_date'       => 'nullable|date',
            'deadline'         => 'nullable|date',
            'status'           => 'sometimes|in:1,2',
            'phase'            => 'nullable|string|in:' . implode(',', array_keys(Project::PHASES)),
            'category'         => 'nullable|string|in:' . implode(',', Project::CATEGORIES),
            'progress'         => 'nullable|integer|between:0,100',
            'general_contractor' => 'nullable|string|max:255',
            'lead_architect'   => 'nullable|string|max:255',
            'site_info'        => 'nullable|string',
            'assigned_users'   => 'nullable|array',
            'assigned_users.*' => 'integer|exists:users,id',
            'images'           => 'nullable|array',
            'images.*'         => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx|max:10240',
            'image_names'      => 'nullable|array',
            'image_names.*'    => 'nullable|string|max:255',
            'deleted_images'   => 'nullable|array',
            'deleted_images.*' => 'integer|exists:project_images,id',
        ]);

        try {
            // Update project fields (only those present in the request)
            $project->update(array_filter([
                'name'         => $request->input('name',         $project->name),
                'description'  => $request->input('description',  $project->description),
                'location'     => $request->input('location',     $project->location),
                'lat'          => $request->input('lat',          $project->lat),
                'long'         => $request->input('long',         $project->long),
                'total_budget' => $request->input('total_budget', $project->total_budget),
                'used_budget'  => $request->input('used_budget',  $project->used_budget),
                'retainage_rate' => $request->input('retainage_rate', $project->retainage_rate),
                'start_date'   => $request->input('start_date',   $project->start_date?->format('Y-m-d')),
                'deadline'     => $request->input('deadline',     $project->deadline?->format('Y-m-d')),
                'status'       => $request->input('status',       $project->status),
                'phase'        => $request->filled('phase') ? Project::PHASES[$request->phase] : $project->phase,
                'category'     => $request->input('category',     $project->category),
                'progress'     => $request->input('progress',     $project->progress),
                'general_contractor' => $request->input('general_contractor', $project->general_contractor),
                'lead_architect'     => $request->input('lead_architect',     $project->lead_architect),
                'site_info'          => $request->input('site_info',          $project->site_info),
            ], fn ($v) => $v !== null));

            if ($project->wasChanged('phase')) {
                ActivityLog::record($project->id, 'project_phase_changed', 'Project phase changed to ' . Project::PHASE_LABELS[$project->phase], $request->user()->id);
            }

            if ($project->wasChanged('progress')) {
                ActivityLog::record($project->id, 'project_progress_updated', 'Project progress updated to ' . $project->progress . '%', $request->user()->id);
            }

            // Sync assigned users if provided
            if ($request->has('assigned_users')) {
                $project->users()->sync($request->assigned_users ?? []);
            }

            // Delete removed images
            if ($request->filled('deleted_images')) {
                $toDelete = \App\Models\ProjectImage::whereIn('id', $request->deleted_images)
                    ->where('project_id', $project->id)
                    ->get();

                foreach ($toDelete as $img) {
                    if (Storage::disk('public')->exists($img->image_path)) {
                        Storage::disk('public')->delete($img->image_path);
                    }
                    $img->delete();
                }
            }

            // Upload new images
            if ($request->hasFile('images')) {
                $this->storeImages($project, $request);
            }

            return response()->json([
                'message' => 'Project updated successfully.',
                'data'    => $this->detailResource($project->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('API update project: ' . $e->getMessage());
            return response()->json(['message' => 'Server error.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a single project document/image.
     *
     * DELETE /api/v1/projects/{id}/images/{imageId}
     */
    public function deleteImage(Request $request, int $id, int $imageId): JsonResponse
    {
        $project = Project::find($id);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $image = ProjectImage::where('project_id', $id)->where('id', $imageId)->first();

        if (! $image) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        try {
            // Remove physical file
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
            $image->delete();

            return response()->json(['message' => 'Document deleted successfully.']);

        } catch (\Exception $e) {
            Log::error('API delete project image: ' . $e->getMessage());
            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    /**
     * Toggle project status between active and inactive.
     *
     * POST /api/v1/projects/{id}/toggle-status
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $project = Project::find($id);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $project->status = $project->status === 1 ? 2 : 1;
        $project->save();

        return response()->json([
            'message'      => 'Project status updated successfully.',
            'project_id'   => $project->id,
            'status'       => $project->status,
            'status_label' => $project->status === 1 ? 'Active' : 'Inactive',
        ]);
    }
    public function assign(Request $request, int $id): JsonResponse
    {
        $project = Project::find($id);

        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        try {
            $project->users()->sync($request->user_ids);
            $project->loadCount('users');

            return response()->json([
                'message'        => 'Users assigned successfully.',
                'project_id'     => $project->id,
                'project_name'   => $project->name,
                'users_count'    => $project->users_count,
                'assigned_users' => $project->users()->get()->map(fn ($u) => [
                    'id'        => $u->id,
                    'full_name' => $u->full_name,
                    'email'     => $u->email,
                    'role'      => $u->getRoleNames()->first(),
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('API assign project users: ' . $e->getMessage());
            return response()->json(['message' => 'Server error.'], 500);
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function storeImages(Project $project, Request $request): void
    {
        $names = $request->input('image_names', []);

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store("project_documents/{$project->id}", 'public');

            ProjectImage::create([
                'project_id' => $project->id,
                'name'       => $names[$index] ?? $file->getClientOriginalName(),
                'image_path' => $path,
            ]);
        }
    }

    private function imageResource(ProjectImage $image): array
    {
        return [
            'id'         => $image->id,
            'name'       => $image->name,
            'url'        => url('storage/' . $image->image_path),
            'created_at' => $image->created_at?->toDateTimeString(),
        ];
    }

    private function listResource(Project $project): array
    {
        return [
            'id'            => $project->id,
            'project_code'  => $project->project_code,
            'name'          => $project->name,
            'location'      => $project->location,
            'category'      => $project->category,
            'total_budget'  => $project->total_budget,
            'used_budget'   => $project->used_budget,
            'progress'      => $project->progress,
            'assigned_date' => $this->assignedDate($project),
            'start_date'    => $project->start_date?->format('Y-m-d'),
            'deadline'      => $project->deadline?->format('Y-m-d'),
            'phase'         => array_search($project->phase, Project::PHASES, true) ?: null,
            'phase_label'   => Project::PHASE_LABELS[$project->phase] ?? null,
            'status'        => $project->status,
            'status_label'  => $project->status === 1 ? 'Active' : 'Inactive',
        ];
    }

    private function assignedDate(Project $project): ?string
    {
        $pivot = $project->pivot
            ?? $project->users()->where('users.id', auth()->id())->first()?->pivot;

        return $pivot?->created_at?->format('Y-m-d');
    }

    private function detailResource(Project $project): array
    {
        $project->loadMissing('users', 'images');

        $remaining = $project->total_budget !== null
            ? $project->total_budget - $project->used_budget
            : null;

        return [
            'id'             => $project->id,
            'project_code'   => $project->project_code,
            'name'           => $project->name,
            'description'    => $project->description,
            'location'       => $project->location,
            'lat'            => $project->lat,
            'long'           => $project->long,
            'category'       => $project->category,
            'general_contractor' => $project->general_contractor,
            'lead_architect' => $project->lead_architect,
            'site_info'      => $project->site_info,
            'progress'       => $project->progress,
            'budget'         => [
                'total'     => $project->total_budget,
                'used'      => $project->used_budget,
                'remaining' => $remaining,
            ],
            'total_budget'   => $project->total_budget,
            'initial_budget' => $project->initial_budget,
            'budget_variance_percent' => $project->budgetVariancePercent(),
            'retainage_rate' => $project->retainage_rate,
            'assigned_date'  => $this->assignedDate($project),
            'start_date'     => $project->start_date?->format('Y-m-d'),
            'deadline'       => $project->deadline?->format('Y-m-d'),
            'quarter_range'  => $project->quarterLabel(),
            'phase'          => array_search($project->phase, Project::PHASES, true) ?: null,
            'phase_label'    => Project::PHASE_LABELS[$project->phase] ?? null,
            'status'         => $project->status,
            'status_label'   => $project->status === 1 ? 'Active' : 'Inactive',
            'supervisors_count'   => $project->users()->role('SUPERVISOR')->count(),
            'pending_tasks_count' => $project->tasks()->whereIn('status', [1, 2, 3])->count(),
            'assigned_users' => $project->users->map(fn ($u) => [
                'id'        => $u->id,
                'full_name' => $u->full_name,
                'email'     => $u->email,
                'role'      => $u->getRoleNames()->first(),
            ]),
            'images'         => $project->images->map(fn ($img) => $this->imageResource($img)),
            'created_at'     => $project->created_at?->toDateTimeString(),
            'updated_at'     => $project->updated_at?->toDateTimeString(),
        ];
    }
}
