<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with([
                'student',
                'universityRoles.role',
                'universityRoles.university',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function show(User $user)
    {
        return response()->json([
            'user' => $user->load([
                'student',
                'universityRoles.role',
                'universityRoles.university',
            ]),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'اطلاعات کاربر به‌روزرسانی شد.',
            'user' => $user->fresh(),
        ]);
    }
}
