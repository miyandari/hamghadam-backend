<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupRegistrationController extends Controller
{
    private function assertEligible(Request $request, Event $event): void
    {
        $student = $request->user()->student;
        if (!$student || $student->status !== 'active') {
            throw ValidationException::withMessages(['student' => ['ابتدا اطلاعات دانشجویی فعال خود را تکمیل کنید.']]);
        }
        if ($student->university_id !== $event->university_id) {
            throw ValidationException::withMessages(['university' => ['ثبت‌نام فقط برای دانشجویان دانشگاه برگزارکننده مجاز است.']]);
        }
        if (!$event->is_group_registration_allowed) {
            throw ValidationException::withMessages(['group' => ['ثبت‌نام گروهی برای این برنامه فعال نیست.']]);
        }
        if ($event->status !== 'published' || now()->lt($event->registration_starts_at) || now()->gt($event->registration_ends_at)) {
            throw ValidationException::withMessages(['registration' => ['مهلت ثبت‌نام این برنامه فعال نیست.']]);
        }
    }

    private function registrationStatus(Event $event): string
    {
        $policy = $event->policy;
        return $policy?->lottery_enabled ? 'pending' : ($policy?->requires_approval ? 'pending_approval' : 'confirmed');
    }

    public function create(Request $request, Event $event)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->assertEligible($request, $event);

        return DB::transaction(function () use ($request, $event, $data) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (Registration::where('event_id', $event->id)->where('user_id', $request->user()->id)->exists()) {
                throw ValidationException::withMessages(['registration' => ['برای این برنامه قبلاً ثبت‌نام کرده‌اید.']]);
            }
            if (!$event->policy?->lottery_enabled && $event->registrations()->whereIn('status', ['confirmed', 'pending_approval'])->count() >= $event->capacity) abort(422, 'ظرفیت برنامه تکمیل است.');
            $group = RegistrationGroup::create([
                'event_id' => $event->id,
                'leader_user_id' => $request->user()->id,
                'name' => $data['name'],
                'invite_code' => Str::upper(Str::random(10)),
                'max_members' => min((int) ($event->policy?->max_group_size ?? 1), 100),
                'status' => 'open',
            ]);
            $registration = Registration::create([
                'event_id' => $event->id, 'user_id' => $request->user()->id,
                'registration_group_id' => $group->id, 'registration_type' => 'group',
                'status' => $this->registrationStatus($event), 'registered_at' => now(), 'confirmed_at' => $this->registrationStatus($event) === 'confirmed' ? now() : null,
            ]);
            return response()->json(['message' => 'گروه ایجاد شد. کد دعوت را برای اعضا ارسال کنید.', 'group' => $group, 'registration' => $registration], 201);
        });
    }

    public function join(Request $request)
    {
        $data = $request->validate(['invite_code' => ['required', 'string', 'max:32']]);
        $group = RegistrationGroup::where('invite_code', Str::upper($data['invite_code']))->firstOrFail();
        $event = $group->event()->with('policy')->firstOrFail();
        $this->assertEligible($request, $event);

        return DB::transaction(function () use ($request, $group, $event) {
            $group = RegistrationGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($group->status !== 'open') abort(422, 'پذیرش عضو جدید در این گروه بسته شده است.');
            if ($group->registrations()->whereNotIn('status', ['cancelled', 'rejected', 'withdrawn'])->count() >= $group->max_members) abort(422, 'ظرفیت گروه تکمیل است.');
            if (Registration::where('event_id', $event->id)->where('user_id', $request->user()->id)->exists()) {
                abort(422, 'برای این برنامه قبلاً ثبت‌نام کرده‌اید.');
            }
            if (!$event->policy?->lottery_enabled && $event->registrations()->whereIn('status', ['confirmed', 'pending_approval'])->count() >= $event->capacity) abort(422, 'ظرفیت برنامه تکمیل است.');
            $registration = Registration::create([
                'event_id' => $event->id, 'user_id' => $request->user()->id,
                'registration_group_id' => $group->id, 'registration_type' => 'group',
                'status' => $this->registrationStatus($event), 'registered_at' => now(), 'confirmed_at' => $this->registrationStatus($event) === 'confirmed' ? now() : null,
            ]);
            if ($group->registrations()->whereNotIn('status', ['cancelled', 'rejected', 'withdrawn'])->count() >= $group->max_members) $group->update(['status' => 'full']);
            return response()->json(['message' => 'عضویت در گروه با موفقیت انجام شد.', 'registration' => $registration]);
        });
    }

    public function mine(Request $request)
    {
        return response()->json(['groups' => RegistrationGroup::with(['event:id,title,starts_at', 'registrations.user:id,name'])
            ->whereHas('registrations', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest()->get()]);
    }
}
