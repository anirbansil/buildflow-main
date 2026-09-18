<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    protected int $per_page = 10;

    public function index()
    {
        try {
            $users = User::filter(Request::only('name', 'email', 'phone', 'status', 'role'))
                ->role(['OWNER', 'SUPERVISOR', 'WORKER'])
                ->ordering(Request::only('field_name', 'short_by'))
                ->orderBy('id', 'desc')
                ->paginate(request()->perPage ?? $this->per_page)
                ->withQueryString()
                ->through(fn($user) => [
                    'id'            => $user->id,
                    'full_name'     => $user->full_name,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'status'        => $user->status,
                    'role'          => $user->getRoleNames()->first() ?? '',
                    'profile_photo' => $user->profile_photo
                        ? '/storage/' . $user->profile_photo
                        : null,
                ]);

            $filters = Request::all('name', 'email', 'phone', 'status', 'role');

            return Inertia::render('admin/users/index', compact('filters', 'users'));
        } catch (\Exception $e) {
            Log::error(':: EXCEPTION :: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Server error');
        }
    }

    public function create()
    {
        return Inertia::render('admin/users/create-edit');
    }

    public function store()
    {
        request()->validate([
            'first_name'    => 'required|max:40',
            'last_name'     => 'required|max:40',
            'email'         => 'required|email|unique:users,email',
            'password'      => ['required', 'string', Password::min(8)],
            'phone'         => 'required|numeric',
            'dob'           => 'nullable|date|before:5 years ago',
            'profile_photo' => 'nullable|image|max:2048',
            'status'        => 'required',
            'role'          => 'required|in:OWNER,SUPERVISOR,WORKER',
        ]);

        $user = new User();
        $user->first_name = request()->first_name;
        $user->last_name  = request()->last_name;
        $user->email      = request()->email;
        $user->password   = request()->password;
        $user->phone      = request()->phone;
        $user->dob        = request()->dob ? date('Y-m-d', strtotime(request()->dob)) : null;
        $user->status     = request()->status ?? 1;
        $user->profile_photo = Request::file('profile_photo')
            ? Request::file('profile_photo')->store('profile_photo', 'public')
            : null;
        $user->save();
        $user->assignRole(request()->role ?? 'WORKER');

        session()->flash('success', 'User successfully created');
        return redirect()->route('admin.users.index');
    }

    public function edit(User $user)
    {
        $userData = [
            'id'            => $user->id,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'dob'           => $user->dob,
            'status'        => $user->status,
            'role'          => $user->getRoleNames()->first() ?? 'WORKER',
            'profile_photo' => $user->profile_photo ? '/storage/' . $user->profile_photo : null,
        ];

        return Inertia::render('admin/users/create-edit', ['user' => $userData]);
    }

    public function update(User $user)
    {
        request()->validate([
            'first_name' => 'required|max:40',
            'last_name'  => 'required|max:40',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'phone'      => 'required|unique:users,phone,' . $user->id,
            'dob'        => 'nullable|date|before:5 years ago',
            'status'     => 'required',
            'role'       => 'required|in:OWNER,SUPERVISOR,WORKER',
        ]);

        $user->first_name = request()->first_name;
        $user->last_name  = request()->last_name;
        $user->email      = request()->email;
        $user->phone      = request()->phone;
        $user->dob        = request()->dob ? date('Y-m-d', strtotime(request()->dob)) : null;
        $user->status     = request()->status ?? 1;

        if (Request::hasFile('profile_photo')) {
            if ($user->profile_photo) {
                File::delete(storage_path('app/public/' . $user->profile_photo));
            }
            $user->profile_photo = Request::file('profile_photo')->store('profile_photo', 'public');
        }

        $user->save();
        $user->syncRoles([request()->role]);

        session()->flash('success', 'User successfully updated');
        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user)
    {
        try {
            if ($user->profile_photo) {
                File::delete(storage_path('app/public/' . $user->profile_photo));
            }
            $user->delete();
            session()->flash('success', 'User successfully deleted');
            return back();
        } catch (\Exception $e) {
            Log::error(':: EXCEPTION :: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Server error');
        }
    }

    public function changeStatus(User $user)
    {
        try {
            $user->status = ($user->status == 1) ? 2 : 1;
            $user->save();
            session()->flash('success', 'User status successfully changed');
            return back();
        } catch (\Exception $e) {
            Log::error(':: EXCEPTION :: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Server error');
        }
    }
}
