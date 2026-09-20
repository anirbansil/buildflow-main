<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    private const LANGUAGES = [
        'en' => 'English',
        'bn' => 'Bengali',
    ];

    /**
     * Get the user's preferences (Settings screen).
     *
     * GET /api/v1/settings
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->resource($request->user())]);
    }

    /**
     * Update preferences. Only the fields sent are changed.
     *
     * POST /api/v1/settings
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'notifications_enabled' => 'sometimes|required|boolean',
            'language' => 'sometimes|required|string|in:'.implode(',', array_keys(self::LANGUAGES)),
        ]);

        $user = $request->user();
        $user->update($request->only(['notifications_enabled', 'language']));

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data' => $this->resource($user->fresh()),
        ]);
    }

    /**
     * Change password. Requires the current password; every other device is signed out.
     *
     * POST /api/v1/settings/password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $user = $request->user();
        $user->update(['password' => $request->password]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    private function resource(User $user): array
    {
        return [
            'notifications_enabled' => (bool) $user->notifications_enabled,
            'language' => $user->language,
            'language_label' => self::LANGUAGES[$user->language] ?? $user->language,
            'available_languages' => collect(self::LANGUAGES)
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                ->values(),
        ];
    }
}
