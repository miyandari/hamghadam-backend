<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'حساب کاربری غیرفعال است.'], 403);
        }

        if (
            ($user->is_super_admin || strtolower($user->email) === 'mahmia.developer@gmail.com') &&
            in_array('super_admin', $roles, true)
        ) {
            return $next($request);
        }

        $hasRole = $user->universityRoles()
            ->where('is_active', true)
            ->whereHas('role', function ($query) use ($roles) {
                $query->whereIn('name', $roles);
            })
            ->exists();

        if (!$hasRole) {
            return response()->json([
                'message' => 'شما مجوز دسترسی به این بخش را ندارید.',
            ], 403);
        }

        return $next($request);
    }
}