<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $registrations = Registration::with('event')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($registrations);
    }

    public function store(Request $request, Event $event)
    {
        $user = $request->user();

        if (!$user->student || $user->student->status !== 'active') {
            throw ValidationException::withMessages([
                'student' => ['ابتدا باید اطلاعات دانشجویی خود را تکمیل کنید.'],
            ]);
        }

        if ($user->student->university_id !== $event->university_id) {
            throw ValidationException::withMessages([
                'university' => ['ثبت‌نام فقط برای دانشجویان دانشگاه برگزارکننده مجاز است.'],
            ]);
        }

        return DB::transaction(function () use ($user, $event) {
            $event = Event::whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();

            if ($event->status !== 'published') {
                throw ValidationException::withMessages([
                    'event' => ['این رویداد در حال حاضر قابل ثبت‌نام نیست.'],
                ]);
            }

            if (
                $now->lt($event->registration_starts_at) ||
                $now->gt($event->registration_ends_at)
            ) {
                throw ValidationException::withMessages([
                    'registration' => ['مهلت ثبت‌نام این رویداد فعال نیست.'],
                ]);
            }

            if (Registration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'registration' => ['شما قبلاً در این رویداد ثبت‌نام کرده‌اید.'],
                ]);
            }

            $occupied = Registration::where('event_id', $event->id)
                ->whereNotIn('status', ['cancelled', 'rejected', 'withdrawn'])
                ->count();

            if ($occupied >= $event->capacity) {
                $position = (int) WaitlistEntry::where('event_id', $event->id)
                    ->where('status', 'waiting')->max('position') + 1;

                $entry = WaitlistEntry::firstOrCreate(
                    ['event_id' => $event->id, 'user_id' => $user->id],
                    ['position' => $position, 'status' => 'waiting']
                );

                return response()->json([
                    'message' => 'ظرفیت تکمیل است؛ در فهرست انتظار قرار گرفتید.',
                    'waitlist' => $entry,
                ], 202);
            }

            $requiresApproval = (bool) optional($event->policy)->requires_approval;

            $registration = Registration::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => $requiresApproval ? 'pending' : 'confirmed',
                'registration_type' => 'individual',
                'registered_at' => $now,
                'confirmed_at' => $requiresApproval ? null : $now,
            ]);

            return response()->json([
                'message' => 'ثبت‌نام با موفقیت انجام شد.',
                'registration' => $registration->load('event'),
            ], 201);
        });
    }

    public function show(Request $request, Registration $registration)
    {
        abort_unless(
            $registration->user_id === $request->user()->id,
            403
        );

        return response()->json([
            'registration' => $registration->load('event'),
        ]);
    }

    public function cancel(Request $request, Registration $registration)
    {
        abort_unless(
            $registration->user_id === $request->user()->id,
            403
        );

        if (in_array($registration->status, ['cancelled', 'rejected', 'withdrawn'], true)) {
            throw ValidationException::withMessages([
                'registration' => ['این ثبت‌نام قبلاً لغو شده است.'],
            ]);
        }

        $registration->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'ثبت‌نام لغو شد.',
        ]);
    }
}
