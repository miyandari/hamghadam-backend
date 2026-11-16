<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UniversityController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\StudentCulturalRecordController;
use App\Http\Controllers\Api\GroupRegistrationController;
use App\Http\Controllers\Api\LotteryController;

Route::prefix('v1')->group(function () {

    // احراز هویت
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // فهرست عمومی دانشگاه‌ها و رویدادهای منتشرشده
    Route::get('/universities', [UniversityController::class, 'index']);
    Route::get('/events', [EventController::class, 'publicIndex']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/student', [StudentController::class, 'show']);
        Route::post('/student', [StudentController::class, 'store']);
        Route::put('/student', [StudentController::class, 'update']);
        Route::get('/student/cultural-records', [StudentCulturalRecordController::class, 'index']);
        Route::post('/student/cultural-records', [StudentCulturalRecordController::class, 'store']);
        Route::put('/student/cultural-records/{record}', [StudentCulturalRecordController::class, 'update']);
        Route::delete('/student/cultural-records/{record}', [StudentCulturalRecordController::class, 'destroy']);
        Route::post('/groups/join', [GroupRegistrationController::class, 'join']);
        Route::get('/my-groups', [GroupRegistrationController::class, 'mine']);
        Route::get('/events/{event}/albums', [AlbumController::class, 'index']);

        // حساب کاربری
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);

        // ثبت‌نام دانشجویان
        Route::get('/my-registrations', [RegistrationController::class, 'index']);

        Route::post(
            '/events/{event}/register',
            [RegistrationController::class, 'store']
        );
        Route::post('/events/{event}/groups', [GroupRegistrationController::class, 'create']);

        Route::get(
            '/registrations/{registration}',
            [RegistrationController::class, 'show']
        );

        Route::delete(
            '/registrations/{registration}',
            [RegistrationController::class, 'cancel']
        );

        // اعلان‌های شخصی و بازخورد رویداد
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::post('/events/{event}/feedback', [FeedbackController::class, 'store']);
        Route::get('/events/{event}/feedback', [FeedbackController::class, 'publicIndex']);

        // رویدادهای فرهنگی
        Route::middleware('role:super_admin,cultural_manager')->group(function () {
            Route::get('/managed-events', [EventController::class, 'index']);
            Route::get('/managed-events/{event}', [EventController::class, 'show']);
        });

        // ایجاد و ویرایش برنامه فقط در اختیار مدیر فرهنگی است.
        Route::middleware('role:cultural_manager')->group(function () {
            Route::post('/events', [EventController::class, 'store']);
            Route::put('/events/{event}', [EventController::class, 'update']);
            Route::patch('/events/{event}', [EventController::class, 'update']);
            Route::delete('/events/{event}', [EventController::class, 'destroy']);

            // امتیازدهی و نتایج
            Route::get('/events/{event}/scoring-criteria', [ScoringController::class, 'criteria']);
            Route::post('/events/{event}/scoring-criteria', [ScoringController::class, 'storeCriterion']);
            Route::put('/events/{event}/scoring-criteria/{criterion}', [ScoringController::class, 'updateCriterion']);
            Route::post('/events/{event}/registrations/{registration}/scores', [ScoringController::class, 'score']);
            Route::get('/events/{event}/scoring-results', [ScoringController::class, 'results']);
            Route::get('/events/{event}/lottery-applicants', [LotteryController::class, 'applicants']);
            Route::post('/events/{event}/lottery-factor-awards', [LotteryController::class, 'awardFactor']);
            Route::post('/events/{event}/run-lottery', [LotteryController::class, 'run']);
            Route::get('/events/{event}/lottery-results', [LotteryController::class, 'results']);
            Route::post('/events/{event}/registrations/{registration}/manager-approve', [LotteryController::class, 'approve']);
            Route::patch('/events/{event}/registrations/{registration}/moderate', [LotteryController::class, 'moderateRegistration']);
            Route::get('/students/pending', [StudentController::class, 'pending']);
            Route::patch('/students/{student}/moderate', [StudentController::class, 'moderate']);
            Route::patch('/student-cultural-records/{record}/moderate', [StudentCulturalRecordController::class, 'moderate']);

        });

        // گزارش‌ها و امکانات نظارتی برای مدیر ارشد و مدیر فرهنگی
        Route::middleware('role:super_admin,cultural_manager')->group(function () {
            Route::post('/events/{event}/albums', [AlbumController::class, 'store']);
            Route::post('/events/{event}/albums/{album}/media', [AlbumController::class, 'upload']);
            Route::delete('/events/{event}/albums/{album}/media/{media}', [AlbumController::class, 'destroyMedia']);
            Route::delete('/events/{event}/albums/{album}', [AlbumController::class, 'destroy']);
            Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
            Route::get('/reports/events/{event}', [ReportController::class, 'event']);
            Route::get('/events/{event}/feedback-management', [FeedbackController::class, 'manageIndex']);
            Route::patch('/events/{event}/feedback/{feedback}', [FeedbackController::class, 'moderate']);
            Route::post('/events/{event}/notifications', [NotificationController::class, 'sendEvent']);
        });

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

            Route::post('/notifications/send', [NotificationController::class, 'send']);

            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::patch('/users/{user}/status', [UserController::class, 'setActive']);
            Route::get('/users/{user}', [UserController::class, 'show']);
            Route::put('/users/{user}', [UserController::class, 'update']);
        });
    });
});
