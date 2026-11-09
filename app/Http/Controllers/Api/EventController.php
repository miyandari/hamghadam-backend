<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    private function manageableUniversities(Request $request)
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            return null;
        }

        return $user->universityRoles()
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->where('name', 'cultural_manager');
            })
            ->pluck('university_id');
    }

    private function authorizeUniversity(Request $request, int $universityId): void
    {
        $universities = $this->manageableUniversities($request);

        abort_unless(
            $universities === null || $universities->contains($universityId),
            403,
            'شما مجوز مدیریت رویدادهای این دانشگاه را ندارید.'
        );
    }

    public function index(Request $request)
    {
        $universities = $this->manageableUniversities($request);

        $events = Event::with(['university', 'policy'])
            ->when(
                $universities !== null,
                fn ($query) => $query->whereIn('university_id', $universities)
            )
            ->when(
                $request->filled('university_id'),
                fn ($query) => $query->where(
                    'university_id',
                    $request->university_id
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->status)
            )
            ->orderByDesc('starts_at')
            ->paginate(20);

        return response()->json($events);
    }

    public function show(Request $request, Event $event)
    {
        $this->authorizeUniversity($request, $event->university_id);

        return response()->json([
            'event' => $event->load([
                'university',
                'policy',
                'scoringCriteria',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'university_id' => ['required', 'exists:universities,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'venue' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'registration_starts_at' => ['required', 'date'],
            'registration_ends_at' => [
                'required',
                'date',
                'after:registration_starts_at',
                'before_or_equal:starts_at',
            ],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'max:50'],
            'is_group_registration_allowed' => ['sometimes', 'boolean'],
        ]);

        $this->authorizeUniversity($request, $validated['university_id']);

        $validated['created_by'] = $request->user()->id;

        $event = Event::create($validated);

        return response()->json([
            'message' => 'رویداد فرهنگی ایجاد شد.',
            'event' => $event,
        ], 201);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeUniversity($request, $event->university_id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('events', 'slug')->ignore($event->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'registration_starts_at' => ['sometimes', 'required', 'date'],
            'registration_ends_at' => ['sometimes', 'required', 'date'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
            'is_group_registration_allowed' => ['sometimes', 'boolean'],
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'رویداد به‌روزرسانی شد.',
            'event' => $event->fresh(),
        ]);
    }

    public function destroy(Request $request, Event $event)
    {
        $this->authorizeUniversity($request, $event->university_id);

        $event->delete();

        return response()->json([
            'message' => 'رویداد حذف شد.',
        ]);
    }
}
