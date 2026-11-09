<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UniversityController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\StudentController;

Route::prefix('v1')->group(function () {

    // احراز هویت
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // دانشگاه‌ها
    Route::get('/universities', [UniversityController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/student', [StudentController::class, 'show']);
        Route::post('/student', [StudentController::class, 'store']);
        Route::put('/student', [StudentController::class, 'update']);

        // حساب کاربری
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);

        // ثبت‌نام دانشجویان
        Route::get('/my-registrations', [RegistrationController::class, 'index']);

        Route::post(
            '/events/{event}/register',
            [RegistrationController::class, 'store']
        );

        Route::get(
            '/registrations/{registration}',
            [RegistrationController::class, 'show']
        );

        Route::delete(
            '/registrations/{registration}',
            [RegistrationController::class, 'cancel']
        );

        // رویدادهای فرهنگی
        Route::middleware('role:super_admin,cultural_manager')
            ->apiResource('events', EventController::class);

        // مدیریت سراسری
        Route::middleware('role:super_admin')->group(function () {

            Route::post('/universities', [UniversityController::class, 'store']);
            Route::get('/universities/{university}', [UniversityController::class, 'show']);
            Route::put('/universities/{university}', [UniversityController::class, 'update']);

            Route::post(
                '/universities/{university}/roles',
                [UniversityController::class, 'assignRole']
            );

            Route::delete(
                '/universities/{university}/roles/{user}',
                [UniversityController::class, 'removeRole']
            );

            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/{user}', [UserController::class, 'show']);
            Route::put('/users/{user}', [UserController::class, 'update']);
        });
    });
});
