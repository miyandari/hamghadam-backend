<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\AuditLog;
use App\Models\EventLotteryFactorAward;
use App\Models\EventLotteryRun;
use App\Models\Registration;
use App\Models\StudentCulturalRecord;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LotteryController extends Controller
{
    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();
        abort_unless($user->is_super_admin || $user->universityRoles()->where('university_id', $event->university_id)->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'cultural_manager'))->exists(), 403);
    }

    private function configuredFactors(Event $event): array
    {
        return collect($event->policy?->rules['lottery_factors'] ?? [])->filter(fn ($f) => !empty($f['name']) && (float) ($f['coefficient'] ?? 0) > 0)->values()->all();
    }

    public function applicants(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        $registrations = $event->registrations()->with(['user:id,name,email', 'user.student', 'group:id,name,invite_code', 'user.studentCulturalRecords' => fn ($q) => $q->latest()])
            ->whereNotIn('status', ['cancelled', 'rejected'])->orderBy('registered_at')->get();
        $awards = EventLotteryFactorAward::where('event_id', $event->id)->get()->groupBy('user_id');
        return response()->json(['factors' => $this->configuredFactors($event), 'policy' => $event->policy, 'registrations' => $registrations, 'awards' => $awards]);
    }

    public function awardFactor(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        abort_if(EventLotteryRun::where('event_id', $event->id)->exists(), 422, 'پس از اجرای قرعه‌کشی، تغییر عوامل متقاضیان مجاز نیست.');
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'factor_name' => ['required', 'string', 'max:120'],
            'action' => ['required', 'in:verify,revoke'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($event->registrations()->where('user_id', $data['user_id'])->exists(), 404, 'متقاضی این برنامه یافت نشد.');
        $factor = collect($this->configuredFactors($event))->first(fn ($f) => trim($f['name']) === trim($data['factor_name']));
        abort_unless($factor, 422, 'عامل انتخاب‌شده در تنظیمات این برنامه تعریف نشده است.');

        if ($data['action'] === 'revoke') {
            EventLotteryFactorAward::where('event_id', $event->id)->where('user_id', $data['user_id'])->where('factor_name', $data['factor_name'])->delete();
            return response()->json(['message' => 'عامل از سوابق قرعه‌کشی این دانشجو حذف شد.']);
        }
        $award = EventLotteryFactorAward::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $data['user_id'], 'factor_name' => $data['factor_name']],
            ['coefficient' => $factor['coefficient'], 'status' => 'verified', 'verified_by' => $request->user()->id, 'verified_at' => now(), 'note' => $data['note'] ?? null]
        );
        return response()->json(['message' => 'عامل تأیید و در ضریب قرعه‌کشی اعمال شد.', 'award' => $award]);
    }

    private function weightFor(Event $event, Registration $registration, array $factors): float
    {
        static $studentFactorCache = [];
        $cacheKey = $event->id . ':' . $registration->user_id;
        if (!array_key_exists($cacheKey, $studentFactorCache)) {
            $studentFactorCache[$cacheKey] = [
                'awards' => EventLotteryFactorAward::where('event_id', $event->id)->where('user_id', $registration->user_id)->where('status', 'verified')->pluck('factor_name')->map(fn ($name) => trim($name))->all(),
                'record_types' => StudentCulturalRecord::where('user_id', $registration->user_id)->where('status', 'verified')->pluck('record_type')->all(),
                'has_prior' => null,
            ];
        }
        $studentData = $studentFactorCache[$cacheKey];
        $weight = 1.0;

        foreach ($factors as $factor) {
            $factorName = trim($factor['name']);
            $coefficient = max(0.0001, (float) $factor['coefficient']);
            if (in_array($factorName, $studentData['awards'], true)) {
                $weight *= $coefficient;
                continue;
            }

            $name = strtolower($factorName);
            if (str_contains($name, 'اولین') || str_contains($name, 'نخستین')) {
                if ($studentData['has_prior'] === null) {
                    $studentData['has_prior'] = Registration::where('user_id', $registration->user_id)
                        ->where('event_id', '!=', $event->id)
                        ->whereIn('status', ['confirmed', 'pending_approval'])
                        ->whereHas('event', fn ($q) => $q->where('category', $event->category))
                        ->exists();
                }
                if (!$studentData['has_prior']) $weight *= $coefficient;
                continue;
            }

            $recordTypes = [];
            if (str_contains($name, 'تشکل') || str_contains($name, 'عضویت')) $recordTypes[] = 'organization_membership';
            if (str_contains($name, 'فعالیت فرهنگی') || str_contains($name, 'مشارکت فرهنگی')) $recordTypes[] = 'cultural_activity';
            if (str_contains($name, 'داوطلب')) $recordTypes[] = 'volunteer';
            if (str_contains($name, 'تقدیر') || str_contains($name, 'جایزه')) $recordTypes[] = 'award';
            if ($recordTypes && array_intersect($recordTypes, $studentData['record_types'])) $weight *= $coefficient;
        }

        $studentFactorCache[$cacheKey] = $studentData;
        return round(min(max($weight, 0.0001), 1000000), 4);
    }

    private function weightedPick(array $units): ?int
    {
        $total = array_sum(array_column($units, 'weight'));
        if ($total <= 0 || !$units) return null;
        $needle = random_int(1, 1000000000) / 1000000000 * $total;
        foreach ($units as $index => $unit) {
            $needle -= $unit['weight'];
            if ($needle <= 0) return $index;
        }
        return array_key_last($units);
    }

    public function run(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        return DB::transaction(function () use ($request, $event) {
            $event = Event::with('policy')->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless((bool) $event->policy?->lottery_enabled, 422, 'قرعه‌کشی برای این برنامه فعال نیست.');
            if ($event->registration_ends_at && now()->lt($event->registration_ends_at)) abort(422, 'قرعه‌کشی پس از پایان مهلت ثبت‌نام قابل اجراست.');
            if (EventLotteryRun::where('event_id', $event->id)->exists()) abort(422, 'برای این برنامه قبلاً قرعه‌کشی انجام شده است. برای جلوگیری از تغییر نتایج، اجرای مجدد مجاز نیست.');

            $factors = $this->configuredFactors($event);
            $occupied = $event->registrations()->whereIn('status', ['confirmed', 'pending_approval'])->count();
            $slots = max(0, (int) $event->capacity - $occupied);
            $candidates = $event->registrations()->with('group')->where('status', 'pending')->orderBy('id')->get();
            $grouped = $candidates->groupBy(fn ($r) => $r->registration_group_id ? 'g'.$r->registration_group_id : 'r'.$r->id);
            $units = [];
            foreach ($grouped as $key => $members) {
                $weights = $members->map(fn ($r) => $this->weightFor($event, $r, $factors));
                $units[] = ['key' => $key, 'registrations' => $members, 'size' => $members->count(), 'weight' => max(0.0001, $weights->avg())];
            }

            $remaining = $units;
            $winners = [];
            $rank = 0;
            while ($remaining && $slots > 0) {
                $eligible = array_values(array_filter($remaining, fn ($unit) => $unit['size'] <= $slots));
                if (!$eligible) break;
                $pickedIndex = $this->weightedPick($eligible);
                $picked = $eligible[$pickedIndex];
                $rank++;
                foreach ($picked['registrations'] as $registration) {
                    $weight = $this->weightFor($event, $registration, $factors);
                    $registration->update([
                        'status' => $event->policy?->requires_approval ? 'pending_approval' : 'confirmed',
                        'selection_method' => 'lottery', 'lottery_weight' => $weight,
                        'lottery_rank' => $rank, 'lottery_number' => $rank,
                        'confirmed_at' => $event->policy?->requires_approval ? null : now(),
                    ]);
                    $winners[] = ['registration_id' => $registration->id, 'user_id' => $registration->user_id, 'rank' => $rank, 'weight' => $weight];
                }
                $slots -= $picked['size'];
                $remaining = array_values(array_filter($remaining, fn ($unit) => $unit['key'] !== $picked['key']));
            }

            $waitlisted = [];
            $position = (int) WaitlistEntry::where('event_id', $event->id)->where('status', 'waiting')->max('position');
            foreach ($remaining as $unit) {
                foreach ($unit['registrations'] as $registration) {
                    $registration->update(['status' => 'waitlisted', 'selection_method' => 'lottery', 'lottery_weight' => $this->weightFor($event, $registration, $factors)]);
                    $position++;
                    WaitlistEntry::updateOrCreate(['event_id' => $event->id, 'user_id' => $registration->user_id], ['registration_id' => $registration->id, 'position' => $position, 'status' => 'waiting']);
                    $waitlisted[] = ['registration_id' => $registration->id, 'user_id' => $registration->user_id, 'position' => $position];
                }
            }

            $snapshot = ['winners' => $winners, 'waitlist' => $waitlisted, 'factors' => $factors, 'capacity' => $event->capacity, 'already_occupied' => $occupied];
            $run = EventLotteryRun::create(['event_id' => $event->id, 'run_by' => $request->user()->id, 'candidate_count' => $candidates->count(), 'winner_count' => count($winners), 'waitlist_count' => count($waitlisted), 'result_snapshot' => $snapshot, 'executed_at' => now()]);
            $notices = [];
            foreach ($winners as $winner) $notices[] = ['id' => (string) Str::uuid(), 'user_id' => $winner['user_id'], 'channel' => 'database', 'type' => 'App\\Notifications\\LotteryResult', 'data' => json_encode(['title' => 'نتیجه قرعه‌کشی', 'body' => $event->title . '؛ شما در قرعه‌کشی انتخاب شده‌اید.', 'event_id' => $event->id, 'status' => $event->policy?->requires_approval ? 'pending_approval' : 'confirmed'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()];
            foreach ($waitlisted as $entry) $notices[] = ['id' => (string) Str::uuid(), 'user_id' => $entry['user_id'], 'channel' => 'database', 'type' => 'App\\Notifications\\LotteryWaitlist', 'data' => json_encode(['title' => 'فهرست انتظار', 'body' => $event->title . '؛ شما در فهرست انتظار قرار گرفته‌اید.', 'event_id' => $event->id, 'position' => $entry['position']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()];
            if ($notices) DB::table('notifications')->insert($notices);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'event.lottery.executed', 'auditable_type' => Event::class, 'auditable_id' => $event->id, 'new_values' => $snapshot, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            return response()->json(['message' => 'قرعه‌کشی با موفقیت انجام شد.', 'run' => $run, 'winners' => $winners, 'waitlist' => $waitlisted]);
        });
    }

    public function results(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        return response()->json(['runs' => EventLotteryRun::where('event_id', $event->id)->with('runner:id,name')->latest('executed_at')->get(), 'registrations' => $event->registrations()->with('user:id,name,email')->whereIn('status', ['confirmed', 'pending_approval', 'waitlisted'])->orderBy('lottery_rank')->get()]);
    }

    public function approve(Request $request, Event $event, Registration $registration)
    {
        $this->authorizeEvent($request, $event);
        abort_if(EventLotteryRun::where('event_id', $event->id)->exists(), 422, 'سهمیه انتخاب مستقیم باید پیش از اجرای قرعه‌کشی استفاده شود.');
        abort_unless($registration->event_id === $event->id, 404);
        return DB::transaction(function () use ($request, $event, $registration) {
            $group = $registration->registration_group_id;
            $targets = $group
                ? $event->registrations()->where('registration_group_id', $group)->lockForUpdate()->get()
                : collect([$registration->fresh()]);
            if ($targets->isEmpty() || $targets->contains(fn ($r) => !in_array($r->status, ['pending', 'pending_approval', 'waitlisted'], true))) abort(422, 'همه اعضای این گروه در وضعیت قابل انتخاب مستقیم نیستند.');
            $policy = $event->policy;
            $used = $event->registrations()->where('selection_method', 'manager')->whereIn('status', ['confirmed', 'pending_approval'])->count();
            if ($used + $targets->count() > (int) ($policy?->manager_approval_quota ?? 0)) throw ValidationException::withMessages(['quota' => ['سهمیه انتخاب مستقیم مدیر برای این تعداد عضو کافی نیست.']]);
            $occupied = $event->registrations()->whereIn('status', ['confirmed', 'pending_approval'])->count();
            if ($occupied + $targets->count() > $event->capacity) throw ValidationException::withMessages(['capacity' => ['ظرفیت باقی‌مانده برای انتخاب این متقاضی یا گروه کافی نیست.']]);
            foreach ($targets as $target) {
                $target->update(['status' => 'confirmed', 'selection_method' => 'manager', 'confirmed_at' => now()]);
                WaitlistEntry::where('event_id', $event->id)->where('user_id', $target->user_id)->update(['status' => 'promoted', 'offered_at' => now()]);
            }
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'registration.manager_selected', 'auditable_type' => Event::class, 'auditable_id' => $event->id, 'new_values' => ['registration_ids' => $targets->pluck('id')->all(), 'selected_count' => $targets->count()], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            return response()->json(['message' => $group ? 'اعضای گروه با سهمیه مستقیم مدیر انتخاب شدند.' : 'متقاضی با سهمیه مستقیم مدیر انتخاب شد.', 'selected_count' => $targets->count()]);
        });
    }

    public function moderateRegistration(Request $request, Event $event, Registration $registration)
    {
        $this->authorizeEvent($request, $event);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        $targets = $registration->registration_group_id
            ? $event->registrations()->where('registration_group_id', $registration->registration_group_id)->get()
            : collect([$registration]);
        if ($data['decision'] === 'approve') {
            abort_unless($targets->every(fn ($r) => $r->status === 'pending_approval'), 422, 'همه اعضای گروه باید در انتظار تأیید نهایی باشند.');
            foreach ($targets as $target) $target->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        } else {
            abort_unless($targets->every(fn ($r) => in_array($r->status, ['pending', 'pending_approval'], true)), 422, 'این درخواست یا گروه قابل رد نیست.');
            foreach ($targets as $target) $target->update(['status' => 'rejected']);
        }
        return response()->json(['message' => $data['decision'] === 'approve' ? 'ثبت‌نام/گروه تأیید شد.' : 'درخواست/گروه رد شد.', 'affected' => $targets->count()]);
    }
}
