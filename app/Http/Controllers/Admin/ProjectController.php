<?php

namespace App\Http\Controllers\Admin;

use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProjectController extends Controller
{
    protected int $per_page = 10;

    public function index()
    {
        try {
            $projects = Project::filter(Request::only('name', 'location', 'status'))
                ->withCount('users')
                ->ordering(Request::only('field_name', 'short_by'))
                ->orderBy('id', 'desc')
                ->paginate(request()->perPage ?? $this->per_page)
                ->withQueryString()
                ->through(fn ($project) => [
                    'id'           => $project->id,
                    'name'         => $project->name,
                    'location'     => $project->location,
                    'total_budget' => $project->total_budget,
                    'deadline'     => $project->deadline?->format('M d, Y'),
                    'status'       => $project->status,
                    'users_count'  => $project->users_count,
                ]);

            $filters = Request::all('name', 'location', 'status');

            return Inertia::render('admin/projects/index', compact('filters', 'projects'));
        } catch (\Exception $e) {
            Log::error(':: EXCEPTION :: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Server error');
        }
    }

    public function create()
    {
        $users = User::role(['OWNER', 'SUPERVISOR', 'WORKER'])
            ->where('status', 1)
            ->orderBy('first_name')
            ->get()
            ->map(fn ($u) => [
                'id'        => $u->id,
                'full_name' => $u->full_name,
                'role'      => $u->getRoleNames()->first() ?? '',
            ]);

        return Inertia::render('admin/projects/create-edit', compact('users'));
    }

    public function store()
    {
        request()->validate([
            'name'             => 'required|max:150',
            'description'      => 'nullable|string',
            'location'         => 'nullable|max:255',
            'lat'              => 'nullable|numeric|between:-90,90',
            'long'             => 'nullable|numeric|between:-180,180',
            'total_budget'     => 'nullable|numeric|min:0',
            'deadline'         => 'nullable|date',
            'status'           => 'required|in:1,2',
            'assigned_users'   => 'nullable|array',
            'assigned_users.*' => 'exists:users,id',
            'images'           => 'nullable|array',
            'images.*'         => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx|max:10240',
            'image_names'      => 'nullable|array',
            'image_names.*'    => 'nullable|string|max:255',
        ]);

        $project = Project::create([
            'name'         => request()->name,
            'description'  => request()->description,
            'location'     => request()->location,
            'lat'          => request()->lat,
            'long'         => request()->long,
            'total_budget' => request()->total_budget,
            'deadline'     => request()->deadline,
            'status'       => request()->status,
        ]);

        if (request()->filled('assigned_users')) {
            $project->users()->sync(request()->assigned_users);
        }

        if (request()->hasFile('images')) {
            $this->storeImages($project);
        }

        session()->flash('success', 'Project successfully created');
        return redirect()->route('admin.projects.index');
    }

    public function edit(Project $project)
    {
        $users = User::role(['OWNER', 'SUPERVISOR', 'WORKER'])
            ->where('status', 1)
            ->orderBy('first_name')
            ->get()
            ->map(fn ($u) => [
                'id'        => $u->id,
                'full_name' => $u->full_name,
                'role'      => $u->getRoleNames()->first() ?? '',
            ]);

        $projectData = [
            'id'             => $project->id,
            'name'           => $project->name,
            'description'    => $project->description,
            'location'       => $project->location,
            'lat'            => $project->lat,
            'long'           => $project->long,
            'total_budget'   => $project->total_budget,
            'deadline'       => $project->deadline?->format('Y-m-d'),
            'status'         => (string) $project->status,
            'assigned_users' => $project->users->pluck('id')->toArray(),
            'existing_images' => $project->images->map(fn ($img) => [
                'id'   => $img->id,
                'name' => $img->name,
                'url'  => '/storage/' . $img->image_path,
            ])->toArray(),
        ];

        return Inertia::render('admin/projects/create-edit', [
            'project' => $projectData,
            'users'   => $users,
        ]);
    }

    public function update(Project $project)
    {
        request()->validate([
            'name'             => 'required|max:150',
            'description'      => 'nullable|string',
            'location'         => 'nullable|max:255',
            'lat'              => 'nullable|numeric|between:-90,90',
            'long'             => 'nullable|numeric|between:-180,180',
            'total_budget'     => 'nullable|numeric|min:0',
            'deadline'         => 'nullable|date',
            'status'           => 'required|in:1,2',
            'assigned_users'   => 'nullable|array',
            'assigned_users.*' => 'exists:users,id',
            'images'           => 'nullable|array',
            'images.*'         => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx|max:10240',
            'image_names'      => 'nullable|array',
            'image_names.*'    => 'nullable|string|max:255',
            'deleted_images'   => 'nullable|array',
            'deleted_images.*' => 'integer|exists:project_images,id',
        ]);

        $project->update([
            'name'         => request()->name,
            'description'  => request()->description,
            'location'     => request()->location,
            'lat'          => request()->lat,
            'long'         => request()->long,
            'total_budget' => request()->total_budget,
            'deadline'     => request()->deadline,
            'status'       => request()->status,
        ]);

        $project->users()->sync(request()->assigned_users ?? []);

        // Delete removed images
        if (request()->filled('deleted_images')) {
            $toDelete = \App\Models\ProjectImage::whereIn('id', request()->deleted_images)
                ->where('project_id', $project->id)
                ->get();

            foreach ($toDelete as $img) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($img->image_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($img->image_path);
                }
                $img->delete();
            }
        }

        // Upload new images
        if (request()->hasFile('images')) {
            $this->storeImages($project);
        }

        session()->flash('success', 'Project successfully updated');
        return redirect()->route('admin.projects.index');
    }

    public function destroy(Project $project)
    {
        try {
            $project->users()->detach();
            $project->delete();
            session()->flash('success', 'Project successfully deleted');
            return back();
        } catch (\Exception $e) {
            Log::error(':: EXCEPTION :: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Server error');
        }
    }

    public function changeStatus(Project $project)
    {
        try {
            $project->status = ($project->status == 1) ? 2 : 1;
            $project->save();
            session()->flash('success', 'Project status successfully changed');
            return back();
        } catch (\Exception $e) {
            Log::error(':: EXCEPTION :: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Server error');
        }
    }

    private function storeImages(Project $project): void
    {
        $names = request()->input('image_names', []);

        foreach (request()->file('images') as $index => $file) {
            $path = $file->store("project_documents/{$project->id}", 'public');

            ProjectImage::create([
                'project_id' => $project->id,
                'name'       => $names[$index] ?? $file->getClientOriginalName(),
                'image_path' => $path,
            ]);
        }
    }
}
