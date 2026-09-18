<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user with the USER role.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:40'],
            'last_name'  => ['required', 'string', 'max:40'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'password'   => $request->password,
        ]);

        $user->assignRole('OWNER');

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'role'       => $user->getRoleNames()->first(),
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Login — only allows users with the USER role.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->hasRole('OWNER')) {
            return response()->json([
                'message' => 'Access denied. This login is for users only.',
            ], 403);
        }

        if ($user->status != 1) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        // Revoke previous tokens and issue a fresh one
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'role'       => $user->getRoleNames()->first(),
                'status'     => $user->status,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Return the authenticated user's profile with project stats.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // All active projects assigned to this user
        $projects = $user->projects()->where('projects.status', 1)->get();

        $projectIds = $projects->pluck('id');

        // Number of unique supervisors across all those projects
        $supervisorCount = \DB::table('project_assign_users')
            ->join('model_has_roles', 'project_assign_users.user_id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereIn('project_assign_users.project_id', $projectIds)
            ->where('roles.name', 'SUPERVISOR')
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->distinct('project_assign_users.user_id')
            ->count('project_assign_users.user_id');

        return response()->json([
            'user' => [
                'id'            => $user->id,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'full_name'     => $user->full_name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'profile_photo' => $user->profile_photo
                    ? url('storage/' . $user->profile_photo)
                    : null,
                'role'          => $user->getRoleNames()->first(),
                'status'        => $user->status,
            ],
            'stats' => [
                'total_projects'   => $projects->count(),
                'total_budget'     => round($projects->sum('total_budget'), 2),
                'total_supervisors' => $supervisorCount,
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'first_name'    => ['sometimes', 'required', 'string', 'max:40'],
            'last_name'     => ['sometimes', 'required', 'string', 'max:40'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20', 'unique:users,phone,' . $user->id],
            'dob'           => ['sometimes', 'nullable', 'date', 'before:today'],
            'profile_photo' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'password'      => ['sometimes', 'required', 'confirmed', Password::min(8)],
        ]);

        if ($request->filled('first_name'))  $user->first_name = $request->first_name;
        if ($request->filled('last_name'))   $user->last_name  = $request->last_name;
        if ($request->has('phone'))          $user->phone      = $request->phone;
        if ($request->has('dob'))            $user->dob        = $request->dob;
        if ($request->filled('password'))    $user->password   = $request->password;

        if ($request->hasFile('profile_photo')) {
            // Remove old photo
            if ($user->profile_photo) {
                $oldPath = storage_path('app/public/' . $user->profile_photo);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            $user->profile_photo = $request->file('profile_photo')
                ->store('profile_photo', 'public');
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => [
                'id'            => $user->id,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'full_name'     => $user->full_name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'dob'           => $user->dob,
                'profile_photo' => $user->profile_photo
                    ? url('storage/' . $user->profile_photo)
                    : null,
                'role'          => $user->getRoleNames()->first(),
                'status'        => $user->status,
            ],
        ]);
    }

    /**
     * Logout — revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
