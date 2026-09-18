<?php

use Illuminate\Support\Facades\Route;

// Route::inertia('/', 'welcome')->name('home');
Route::get('/', fn () => redirect()->route('login'))->name('home');

// Temporary debug route - remove after debugging
Route::get('/debug-auth', function () {
    $user = auth()->user();
    return response()->json([
        'authenticated' => auth()->check(),
        'user_id'       => $user?->id,
        'user_email'    => $user?->email,
        'roles'         => $user?->getRoleNames()->toArray(),
        'has_super_admin' => $user?->hasRole('SUPER-ADMIN'),
    ]);
});


Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard')->middleware('superadmin');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/artisan.php';
