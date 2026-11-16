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

            $existing = Registration::where('event_id', $event->id)->where('user_id', $user->id)->first();
            if ($existing && !in_array($existing->status, ['cancelled', 'rejected', 'withdrawn'], true)) {
                throw ValidationException::withMessages(['registration' => ['شما قبلاً در این رویداد ثبت‌نام کرده‌اید.']]);
            }

            $occupied = Registration::where('event_id', $event->id)
                ->whereIn('status', ['confirmed', 'pending_approval'])
                ->count();
            $lotteryEnabled = (bool) ($event->policy?->lottery_enabled ?? false);

            if (!$lotteryEnabled && $occupied >= $event->capacity) {
                $registration = Registration::updateOrCreate(
                    ['event_id' => $event->id, 'user_id' => $user->id],
                    ['status' => 'waitlisted', 'registration_type' => 'individual', 'registration_group_id' => null, 'selection_method' => null, 'confirmed_at' => null, 'registered_at' => $now, 'lottery_weight' => 1, 'lottery_rank' => null, 'lottery_number' => null]
                );
                $position = (int) WaitlistEntry::where('event_id', $event->id)->where('status', 'waiting')->max('position') + 1;
                $entry = WaitlistEntry::updateOrCreate(
                    ['event_id' => $event->id, 'user_id' => $user->id],
                    ['registration_id' => $registration->id, 'position' => $position, 'status' => 'waiting']
                );
                return response()->json(['message' => 'ظرفیت تکمیل است؛ در فهرست انتظار قرار گرفتید.', 'registration' => $registration, 'waitlist' => $entry], 202);
            }

            $requiresApproval = (bool) optional($event->policy)->requires_approval;

            $registration = Registration::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $user->id],
                [
                    'status' => ($event->policy?->lottery_enabled ?? false) ? 'pending' : ($requiresApproval ? 'pending_approval' : 'confirmed'),
                    'selection_method' => ($event->policy?->lottery_enabled ?? false) ? null : 'automatic',
                    'registration_type' => 'individual', 'registration_group_id' => null,
                    'registered_at' => $now,
                    'confirmed_at' => (($event->policy?->lottery_enabled ?? false) || $requiresApproval) ? null : $now,
                    'lottery_weight' => 1, 'lottery_rank' => null, 'lottery_number' => null,
                ]
            );

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
        abort_unless($registration->user_id === $request->user()->id, 403);

        return DB::transaction(function () use ($registration) {
            $registration = Registration::with('event.policy')->whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if (in_array($registration->status, ['cancelled', 'rejected', 'withdrawn'], true)) {
                throw ValidationException::withMessages(['registration' => ['این ثبت‌نام قبلاً لغو شده است.']]);
            }
            $releasedSeat = in_array($registration->status, ['confirmed', 'pending_approval'], true);
            $registration->update(['status' => 'cancelled']);
            if ($registration->registration_group_id) {
                \App\Models\RegistrationGroup::whereKey($registration->registration_group_id)->update(['status' => 'open']);
            }
            WaitlistEntry::where('event_id', $registration->event_id)->where('user_id', $registration->user_id)->update(['status' => 'cancelled']);

            if ($releasedSeat) {
                $next = WaitlistEntry::where('event_id', $registration->event_id)->where('status', 'waiting')->orderBy('position')->lockForUpdate()->first();
                if ($next) {
                    $promoted = $next->registration_id
                        ? Registration::whereKey($next->registration_id)->lockForUpdate()->first()
                        : Registration::where('event_id', $registration->event_id)->where('user_id', $next->user_id)->lockForUpdate()->first();
                    if (!$promoted && !$next->registration_id) {
                        $promoted = Registration::create(['event_id' => $registration->event_id, 'user_id' => $next->user_id, 'status' => 'waitlisted', 'registration_type' => 'individual', 'registered_at' => now()]);
                    } elseif ($promoted && in_array($promoted->status, ['cancelled', 'rejected', 'withdrawn'], true)) {
                        $promoted->update(['status' => 'waitlisted', 'registration_type' => 'individual', 'registration_group_id' => null, 'registered_at' => now()]);
                    }
                    if (!$next->registration_id && $promoted) $next->update(['registration_id' => $promoted->id]);
                    if ($promoted && $promoted->status === 'waitlisted') {
                        $unit = $promoted->registration_group_id
                            ? Registration::where('registration_group_id', $promoted->registration_group_id)->where('status', 'waitlisted')->lockForUpdate()->get()
                            : collect([$promoted]);
                        $occupiedNow = $registration->event->registrations()->whereIn('status', ['confirmed', 'pending_approval'])->count();
                        $freeSeats = max(0, (int) $registration->event->capacity - $occupiedNow);
                        if ($unit->count() <= $freeSeats) {
                            $needsApproval = (bool) ($registration->event->policy?->requires_approval ?? false);
                            foreach ($unit as $member) {
                                $member->update(['status' => $needsApproval ? 'pending_approval' : 'confirmed', 'selection_method' => 'waitlist', 'confirmed_at' => $needsApproval ? null : now()]);
                                WaitlistEntry::where('event_id', $registration->event_id)->where('user_id', $member->user_id)->where('status', 'waiting')->update(['status' => 'promoted', 'offered_at' => now()]);
                                DB::table('notifications')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $member->user_id, 'channel' => 'database', 'type' => 'App\\Notifications\\WaitlistPromotion', 'data' => json_encode(['title' => 'ارتقا از فهرست انتظار', 'body' => $registration->event->title . '؛ ظرفیت برای شما آزاد شد.', 'event_id' => $registration->event_id], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                            }
                        }
                    }
                }
            }
            return response()->json(['message' => 'ثبت‌نام لغو شد.']);
        });
    }
}
