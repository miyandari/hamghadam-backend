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

    public function publicIndex(Request $request)
    {
        $events = Event::with('university')
            ->where('status', 'published')
            ->whereHas('university', fn ($query) => $query->where('is_active', true))
            ->when($request->filled('university_id'), fn ($query) => $query->where('university_id', $request->integer('university_id')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();
                $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%"));
            })
            ->orderBy('starts_at')
            ->paginate(20);

        return response()->json($events);
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
        // مدیر فرهنگی معمولاً به یک دانشگاه تخصیص دارد؛ در این حالت
        // دانشگاه از دسترسی فعال او استخراج می‌شود و نیازی به انتخاب تکراری نیست.
        $managedUniversities = $this->manageableUniversities($request);
        if (!$request->filled('university_id') && $managedUniversities !== null) {
            abort_if($managedUniversities->count() !== 1, 422,
                'برای ثبت برنامه باید دانشگاه محل فعالیت مشخص باشد.');
            $request->merge(['university_id' => $managedUniversities->first()]);
        }

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
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'is_group_registration_allowed' => ['sometimes', 'boolean'],
            'policy' => ['sometimes', 'array'],
            'policy.manager_approval_quota' => ['sometimes', 'integer', 'min:0'],
            'policy.max_group_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'policy.lottery_enabled' => ['sometimes', 'boolean'],
            'policy.requires_approval' => ['sometimes', 'boolean'],
            'policy.rules' => ['sometimes', 'array'],
            'policy.rules.lottery_factors' => ['sometimes', 'array', 'max:30'],
            'policy.rules.lottery_factors.*.name' => ['required', 'string', 'max:120'],
            'policy.rules.lottery_factors.*.coefficient' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $this->authorizeUniversity($request, $validated['university_id']);

        $validated['created_by'] = $request->user()->id;
        $policy = $validated['policy'] ?? [];
        unset($validated['policy']);

        $event = Event::create($validated);
        if ($policy) {
            $event->policy()->create($policy);
        }

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
            'status' => ['sometimes', 'required', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'is_group_registration_allowed' => ['sometimes', 'boolean'],
            'policy' => ['sometimes', 'array'],
            'policy.manager_approval_quota' => ['sometimes', 'integer', 'min:0'],
            'policy.max_group_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'policy.lottery_enabled' => ['sometimes', 'boolean'],
            'policy.requires_approval' => ['sometimes', 'boolean'],
            'policy.rules' => ['sometimes', 'array'],
            'policy.rules.lottery_factors' => ['sometimes', 'array', 'max:30'],
            'policy.rules.lottery_factors.*.name' => ['required', 'string', 'max:120'],
            'policy.rules.lottery_factors.*.coefficient' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $policy = $validated['policy'] ?? null;
        unset($validated['policy']);
        $event->update($validated);
        if ($policy !== null) {
            $event->policy()->updateOrCreate(['event_id' => $event->id], $policy);
        }

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
