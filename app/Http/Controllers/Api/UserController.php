<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users with optional filters: name, role.
     *
     * GET /api/v1/users?name=john&role=WORKER&per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('role')) {
            $request->merge(['role' => strtoupper(trim($request->role))]);
        }

        $request->validate([
            'name'     => 'nullable|string|max:100',
            'role'     => 'nullable|string|in:OWNER,SUPERVISOR,WORKER',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = User::query()
            ->when($request->filled('name'), fn ($q) =>
                $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . trim($request->name) . '%'])
            )
            ->when($request->filled('role'), fn ($q) =>
                $q->role($request->role)
            )
            ->role(['OWNER','SUPERVISOR','WORKER'])
            ->orderBy('first_name')
            ->orderBy('last_name');

        $users = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'id'            => $user->id,
                'full_name'     => $user->full_name,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'role'          => $user->getRoleNames()->first(),
                'status'        => $user->status,
                'status_label'  => $user->status === 1 ? 'Active' : 'Inactive',
                'profile_photo' => $user->profile_photo
                    ? url('storage/' . $user->profile_photo)
                    : null,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
                'from'         => $users->firstItem(),
                'to'           => $users->lastItem(),
            ],
        ]);
    }

    /**
     * Set a worker's fixed supervisor and level (Owner Profile → Operational Details).
     * Caller must be an OWNER. `supervisor_id` must have the SUPERVISOR role.
     *
     * POST /api/v1/users/{id}/worker-profile
     */
    public function updateWorkerProfile(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->hasRole('OWNER')) {
            return response()->json(['message' => 'Only an owner can update this.'], 403);
        }

        $worker = User::find($id);

        if (! $worker || ! $worker->hasRole('WORKER')) {
            return response()->json(['message' => 'Worker not found.'], 404);
        }

        $request->validate([
            'supervisor_id' => 'nullable|integer|exists:users,id',
            'worker_level' => 'nullable|integer|between:1,10',
        ]);

        if ($request->filled('supervisor_id')) {
            $supervisor = User::find($request->supervisor_id);

            if (! $supervisor->hasRole('SUPERVISOR')) {
                return response()->json(['message' => 'Selected user is not a supervisor.'], 422);
            }
        }

        $worker->update($request->only(['supervisor_id', 'worker_level']));

        return response()->json([
            'message' => 'Worker profile updated successfully.',
            'data' => [
                'id' => $worker->id,
                'full_name' => $worker->full_name,
                'worker_level' => $worker->worker_level,
                'supervisor' => $worker->supervisor ? [
                    'id' => $worker->supervisor->id,
                    'full_name' => $worker->supervisor->full_name,
                ] : null,
            ],
        ]);
    }
}
