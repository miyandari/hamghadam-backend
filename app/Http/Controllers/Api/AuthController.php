<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'ثبت‌نام موفق بود.',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password) || !$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور اشتباه است.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'ورود موفق بود.',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'خروج موفق بود.',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load([
                'student',
                'universityRoles.role',
                'universityRoles.university',
            ]),
            'permissions' => [
                'is_super_admin' => (bool) ($request->user()->is_super_admin || strtolower($request->user()->email) === 'mahmia.developer@gmail.com'),
            ],
        ]);
    }
}
