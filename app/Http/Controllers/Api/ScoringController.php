<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScoringCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoringController extends Controller
{
    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();
        abort_unless($user->is_super_admin || $user->universityRoles()->where('university_id', $event->university_id)->where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', 'cultural_manager'))->exists(), 403);
    }

    public function criteria(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        return response()->json(['criteria' => $event->scoringCriteria()->orderBy('id')->get()]);
    }

    public function storeCriterion(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        $data = $request->validate(['name'=>'required|string|max:255','description'=>'nullable|string','weight'=>'required|integer|min:1|max:1000','max_score'=>'required|integer|min:1|max:1000','is_active'=>'sometimes|boolean']);
        $criterion = $event->scoringCriteria()->create($data);
        return response()->json(['message'=>'معیار امتیازدهی ایجاد شد.','criterion'=>$criterion], 201);
    }

    public function updateCriterion(Request $request, Event $event, ScoringCriterion $criterion)
    {
        $this->authorizeEvent($request, $event);
        abort_unless($criterion->event_id === $event->id, 404);
        $data = $request->validate(['name'=>'sometimes|required|string|max:255','description'=>'nullable|string','weight'=>'sometimes|required|integer|min:1|max:1000','max_score'=>'sometimes|required|integer|min:1|max:1000','is_active'=>'sometimes|boolean']);
        $criterion->update($data);
        return response()->json(['criterion'=>$criterion->fresh()]);
    }

    public function score(Request $request, Event $event, Registration $registration)
    {
        $this->authorizeEvent($request, $event);
        abort_unless($registration->event_id === $event->id, 404);
        abort_unless($registration->status === 'confirmed', 422, 'امتیازدهی فقط برای ثبت‌نام قطعی امکان‌پذیر است.');
        $data = $request->validate(['scores'=>'required|array|min:1','scores.*.criterion_id'=>'required|integer|distinct|exists:scoring_criteria,id','scores.*.score'=>'required|integer|min:0','scores.*.note'=>'nullable|string|max:5000']);
        $result = DB::transaction(function () use ($data, $event, $registration, $request) {
            foreach ($data['scores'] as $item) {
                $criterion = $event->scoringCriteria()->whereKey($item['criterion_id'])->where('is_active', true)->firstOrFail();
                abort_if($item['score'] > $criterion->max_score, 422, 'امتیاز واردشده از سقف معیار بیشتر است.');
                $registration->scores()->updateOrCreate(['scoring_criterion_id'=>$criterion->id], ['evaluator_id'=>$request->user()->id,'score'=>$item['score'],'note'=>$item['note'] ?? null]);
            }
            $weighted = $registration->scores()->with('criterion')->get()->filter(fn ($s) => $s->criterion && $s->criterion->is_active);
            $weightSum = $weighted->sum(fn ($s) => (float) $s->criterion->weight);
            $total = $weightSum > 0 ? (int) round($weighted->sum(fn ($s) => ((float)$s->score / max(1, (float)$s->criterion->max_score)) * 100 * (float)$s->criterion->weight) / $weightSum) : 0;
            $registration->update(['total_score'=>$total]);
            return ['scores'=>$registration->scores()->with('criterion','evaluator:id,name')->get(),'total_score'=>$total];
        });
        return response()->json($result);
    }

    public function results(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        return response()->json(['results'=>$event->registrations()->with('user:id,name,email','scores.criterion')->where('status', 'confirmed')->orderByDesc('total_score')->paginate(50)]);
    }
}
