<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\University;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UniversityController extends Controller
{
    public function index()
    {
        return response()->json(
            University::where('is_active', true)
                ->orderBy('name')
                ->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:universities,code'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $university = University::create([
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'دانشگاه با موفقیت ایجاد شد.',
            'university' => $university,
        ], 201);
    }

    public function show(University $university)
    {
        return response()->json([
            'university' => $university->load('userRoles.user', 'userRoles.role'),
        ]);
    }

    public function update(Request $request, University $university)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('universities', 'code')->ignore($university->id),
            ],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $university->update($validated);

        return response()->json([
            'message' => 'اطلاعات دانشگاه به‌روزرسانی شد.',
            'university' => $university->fresh(),
        ]);
    }

    public function assignRole(Request $request, University $university)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $role = Role::where('name', $validated['role'])->firstOrFail();

        $university->userRoles()->updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'role_id' => $role->id,
            ],
            [
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'نقش کاربر با موفقیت تخصیص داده شد.',
        ], 201);
    }

    public function removeRole(
        Request $request,
        University $university,
        User $user
    ) {
        $validated = $request->validate([
            'role' => ['required', 'exists:roles,name'],
        ]);

        $role = Role::where('name', $validated['role'])->firstOrFail();

        $assignment = $university->userRoles()
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->first();

        if (!$assignment) {
            return response()->json([
                'message' => 'نقش موردنظر برای این کاربر ثبت نشده است.',
            ], 404);
        }

        $assignment->update(['is_active' => false]);

        return response()->json([
            'message' => 'دسترسی کاربر غیرفعال شد.',
        ]);
    }
}
