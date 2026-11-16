<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'student' => $request->user()->student()->with(['university', 'user.studentCulturalRecords'])->first(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'university_id' => ['required', 'integer', 'exists:universities,id'],
            'student_number' => [
                'required', 'string', 'max:50',
                Rule::unique('students', 'student_number')
                    ->where('university_id', $request->input('university_id'))
                    ->ignore($user->student?->id),
            ],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:100'],
            'entry_year' => ['nullable', 'integer', 'min:1300', 'max:1500'],
            'memberships' => ['sometimes', 'array'],
            'memberships.*' => ['string', Rule::in(['basij', 'islamic_association', 'student_council', 'cultural_active', 'cultural_representative'])],
        ]);

        $university = \App\Models\University::findOrFail($validated['university_id']);
        abort_unless($university->is_active, 422, 'این دانشگاه فعال نیست.');

        $existing = $user->student;
        $sameVerifiedIdentity = $existing
            && $existing->status === 'active'
            && (int) $existing->university_id === (int) $validated['university_id']
            && (string) $existing->student_number === (string) $validated['student_number'];
        $memberships = $validated['memberships'] ?? [];
        unset($validated['memberships']);
        $student = Student::updateOrCreate(
            ['user_id' => $user->id],
            [...$validated, 'status' => $sameVerifiedIdentity ? 'active' : 'pending']
        );

        // هر عضویت انتخاب‌شده به‌صورت یک درخواست مستقل برای بررسی مدیر ثبت می‌شود.
        $membershipTitles = [
            'basij' => 'عضویت در بسیج دانشجویی',
            'islamic_association' => 'عضویت در انجمن اسلامی',
            'student_council' => 'عضویت در شورای صنفی',
            'cultural_active' => 'فعال فرهنگی',
            'cultural_representative' => 'رابط فرهنگی',
        ];
        foreach ($membershipTitles as $key => $title) {
            $record = \App\Models\StudentCulturalRecord::firstOrNew([
                'user_id' => $user->id,
                'record_type' => 'organization_membership',
                'title' => $title,
            ]);
            if (in_array($key, $memberships, true)) {
                if (!$record->exists || $record->status !== 'verified') {
                    $record->fill([
                        'university_id' => $student->university_id,
                        'organization_name' => $title,
                        'description' => 'درخواست اعلام‌شده توسط دانشجو در فرم اطلاعات دانشجویی',
                        'status' => 'pending', 'verified_by' => null, 'verified_at' => null, 'review_note' => null,
                    ])->save();
                }
            } elseif ($record->exists && $record->status === 'pending') {
                $record->delete();
            }
        }

        return response()->json([
            'message' => 'اطلاعات دانشجویی ذخیره شد.',
            'student' => $student->load('university'),
        ], 201);
    }

    public function update(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'ابتدا اطلاعات دانشجویی را ثبت کنید.');

        $validated = $request->validate([
            'student_number' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('students', 'student_number')
                    ->where('university_id', $student->university_id)
                    ->ignore($student->id),
            ],
            'field_of_study' => ['sometimes', 'nullable', 'string', 'max:255'],
            'degree' => ['sometimes', 'nullable', 'string', 'max:100'],
            'entry_year' => ['sometimes', 'nullable', 'integer', 'min:1300', 'max:1500'],
        ]);

        $student->update($validated);

        return response()->json([
            'message' => 'اطلاعات دانشجویی به‌روزرسانی شد.',
            'student' => $student->fresh('university'),
        ]);
    }


    public function pending(Request $request)
    {
        $user = $request->user();
        $universityIds = $user->is_super_admin ? null : $user->universityRoles()->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'cultural_manager'))->pluck('university_id');
        $students = Student::with(['user:id,name,email', 'university:id,name', 'user.studentCulturalRecords' => fn ($q) => $q->where('record_type', 'organization_membership')->latest()])->where(function ($q) { $q->where('status', 'pending')->orWhereHas('user.studentCulturalRecords', fn ($r) => $r->where('record_type', 'organization_membership')->where('status', 'pending')); })
            ->when($universityIds !== null, fn ($q) => $q->whereIn('university_id', $universityIds))->latest()->paginate(30);
        return response()->json($students);
    }

    public function moderate(Request $request, Student $student)
    {
        $user = $request->user();
        abort_unless($user->is_super_admin || $user->universityRoles()->where('university_id', $student->university_id)->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'cultural_manager'))->exists(), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'rejected'])]]);
        $oldStatus = $student->status;
        $student->update(['status' => $data['status']]);
        AuditLog::create(['actor_id' => $user->id, 'action' => 'student.profile_moderated', 'auditable_type' => Student::class, 'auditable_id' => $student->id, 'old_values' => ['status' => $oldStatus], 'new_values' => ['status' => $data['status']], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return response()->json(['message' => $data['status'] === 'active' ? 'اطلاعات دانشجو تأیید شد.' : 'درخواست دانشجو رد شد.', 'student' => $student->fresh('user','university')]);
    }
}
