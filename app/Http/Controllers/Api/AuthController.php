<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            'full_name'    => ['required_without:first_name', 'nullable', 'string', 'max:80'],
            'first_name'   => ['required_without:full_name', 'nullable', 'string', 'max:40'],
            'last_name'    => ['required_with:first_name', 'nullable', 'string', 'max:40'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'        => ['nullable', 'string', 'max:20', $this->uniquePhoneRule()],
            'company_name' => ['nullable', 'string', 'max:255'],
            'password'     => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($request->filled('first_name')) {
            $firstName = $request->first_name;
            $lastName  = $request->last_name;
        } else {
            [$firstName, $lastName] = array_pad(preg_split('/\s+/', trim($request->full_name), 2), 2, null);
        }

        $user = DB::transaction(function () use ($request, $firstName, $lastName) {
            $user = User::create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => $request->password,
            ]);

            $user->assignRole('OWNER');

            if ($request->filled('company_name')) {
                $company = Company::create(['legal_name' => trim($request->company_name)]);
                $user->update(['company_id' => $company->id]);
            }

            return $user;
        });

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $this->authUserResource($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * Login — only allows users with the USER role.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email'      => ['required_without:identifier', 'nullable', 'email'],
            'password'   => ['required', 'string'],
        ]);

        $field      = $request->filled('identifier') ? 'identifier' : 'email';
        $identifier = trim($request->input($field));

        $user = $this->findUserByIdentifier($identifier);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                $field => ['The provided credentials are incorrect.'],
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
            'user'    => $this->authUserResource($user) + ['status' => $user->status],
            'token'   => $token,
        ]);
    }

    /**
     * Send a 6-digit reset code to the account's email (identifier can be email or phone —
     * either way, the code is delivered to the email on file, since there's no SMS gateway).
     * Always returns the same generic message, so this can't be used to discover which
     * emails/phones have accounts.
     *
     * POST /api/v1/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->findUserByIdentifier(trim($request->identifier));
        $generic = ['message' => 'If an account exists, a reset code has been sent to its email address.'];

        if (! $user) {
            return response()->json($generic);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        Mail::raw(
            "Your BuildFlow password reset code is: {$code}\nThis code expires in 15 minutes.",
            fn ($message) => $message->to($user->email)->subject('BuildFlow password reset code')
        );

        return response()->json($generic);
    }

    /**
     * Reset password using the 6-digit code sent by forgot-password.
     * Revokes every existing token, so the user must log in again afterwards.
     *
     * POST /api/v1/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $this->findUserByIdentifier(trim($request->identifier));

        $row = $user ? DB::table('password_reset_tokens')->where('email', $user->email)->first() : null;

        $valid = $row
            && Hash::check($request->code, $row->token)
            && now()->diffInMinutes($row->created_at) <= 15;

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['This reset code is invalid or has expired.'],
            ]);
        }

        $user->update(['password' => $request->password]);
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json(['message' => 'Password reset successfully. Please log in with your new password.']);
    }

    private function authUserResource(User $user): array
    {
        $user->loadMissing('company');

        return [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'full_name'  => $user->full_name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->getRoleNames()->first(),
            'company'    => $user->company ? [
                'id'         => $user->company->id,
                'legal_name' => $user->company->legal_name,
            ] : null,
        ];
    }

    /**
     * Email when the identifier contains "@", otherwise phone (matched on digits only,
     * so "+1 (555) 867-5309" and "15558675309" find the same account).
     */
    private function findUserByIdentifier(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::where('email', $identifier)->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier);

        if ($digits === '') {
            return null;
        }

        return User::whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') = ?", [$digits])->first();
    }

    private function uniquePhoneRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $digits = preg_replace('/\D+/', '', (string) $value);

            if (strlen($digits) < 6) {
                $fail('The phone must contain at least 6 digits.');
            } elseif (User::whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') = ?", [$digits])->exists()) {
                $fail('The phone has already been taken.');
            }
        };
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
