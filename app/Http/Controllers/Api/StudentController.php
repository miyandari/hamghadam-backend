<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'student' => $request->user()->student()->with('university')->first(),
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
        ]);

        $university = \App\Models\University::findOrFail($validated['university_id']);
        abort_unless($university->is_active, 422, 'این دانشگاه فعال نیست.');

        $student = Student::updateOrCreate(
            ['user_id' => $user->id],
            [...$validated, 'status' => 'active']
        );

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
}
