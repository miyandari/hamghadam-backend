<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentCulturalRecord;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentCulturalRecordController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['records' => $request->user()->studentCulturalRecords()->with('verifier:id,name')->latest()->get()]);
    }

    public function store(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student && $student->status === 'active', 422, 'ابتدا اطلاعات دانشجویی فعال را تکمیل کنید.');

        $data = $request->validate([
            'record_type' => ['required', Rule::in(['cultural_activity', 'organization_membership', 'volunteer', 'award', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
        ]);

        $record = StudentCulturalRecord::create([...$data, 'user_id' => $request->user()->id, 'university_id' => $student->university_id, 'status' => 'pending']);
        return response()->json(['message' => 'سابقه برای بررسی مدیر ارسال شد.', 'record' => $record], 201);
    }

    public function update(Request $request, StudentCulturalRecord $record)
    {
        abort_unless($record->user_id === $request->user()->id, 403);
        abort_unless(in_array($record->status, ['pending', 'rejected'], true), 422, 'سابقه تأییدشده قابل ویرایش نیست.');
        $data = $request->validate([
            'record_type' => ['sometimes', 'required', Rule::in(['cultural_activity', 'organization_membership', 'volunteer', 'award', 'other'])],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
        ]);
        $record->update([...$data, 'status' => 'pending', 'verified_by' => null, 'verified_at' => null, 'review_note' => null]);
        return response()->json(['message' => 'سابقه برای بررسی مجدد ارسال شد.', 'record' => $record->fresh()]);
    }

    public function destroy(Request $request, StudentCulturalRecord $record)
    {
        abort_unless($record->user_id === $request->user()->id, 403);
        abort_unless($record->status !== 'verified', 422, 'سابقه تأییدشده قابل حذف نیست.');
        $record->delete();
        return response()->json(['message' => 'سابقه حذف شد.']);
    }

    public function moderate(Request $request, StudentCulturalRecord $record)
    {
        $user = $request->user();
        abort_unless($user->is_super_admin || $user->universityRoles()->where('university_id', $record->university_id)->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'cultural_manager'))->exists(), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $oldStatus = $record->status;
        $record->update(['status' => $data['status'], 'review_note' => $data['review_note'] ?? null, 'verified_by' => $user->id, 'verified_at' => $data['status'] === 'verified' ? now() : null]);
        AuditLog::create(['actor_id' => $user->id, 'action' => 'student.cultural_record_moderated', 'auditable_type' => StudentCulturalRecord::class, 'auditable_id' => $record->id, 'old_values' => ['status' => $oldStatus], 'new_values' => ['status' => $data['status']], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return response()->json(['message' => 'وضعیت سابقه به‌روزرسانی شد.', 'record' => $record->fresh('verifier:id,name')]);
    }
}
