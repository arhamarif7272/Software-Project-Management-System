<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Read-only JSON API (Laravel Sanctum bearer tokens).
 *
 * It reuses the calculation engines of MainController (schedule / CPM, EVM, warnings,
 * change-request impact), so the API and the web pages always show the same numbers.
 * Optional query parameter on every project endpoint: ?status_date=YYYY-MM-DD
 */
class ApiController extends MainController
{
    /** The API is stateless: the status date comes from the query string, not the session. */
    protected function statusDate(): Carbon
    {
        $value = request()->query('status_date');

        if (blank($value)) {
            return Carbon::today();
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $value)->startOfDay();
        } catch (\Throwable) {
            $date = null;
        }

        if (!$date || $date->format('Y-m-d') !== $value) {
            abort(response()->json(['message' => 'status_date must be a valid date in YYYY-MM-DD format.'], 422));
        }

        return $date;
    }

    // =====================================================================
    // Authentication
    // =====================================================================

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'token_type' => 'Bearer',
            'token'      => $user->createToken($data['device_name'] ?? 'api')->plainTextToken,
            'user'       => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }

    // =====================================================================
    // Projects
    // =====================================================================

    private function projectArray(Project $project): array
    {
        return [
            'id'                => $project->id,
            'name'              => $project->name,
            'status'            => $project->status,
            'charter_approved'  => (bool) $project->charter_approved,
            'start_date'        => $project->start_date->toDateString(),
            'approved_budget'   => (float) $project->approved_budget,
            'current_budget'    => (float) $project->current_budget,
            'approved_deadline' => $project->approved_deadline->toDateString(),
            'current_deadline'  => $project->current_deadline->toDateString(),
        ];
    }

    public function projects(): JsonResponse
    {
        return response()->json([
            'data' => Project::latest()->get()->map(fn (Project $p) => $this->projectArray($p))->values(),
        ]);
    }

    public function project(Project $project): JsonResponse
    {
        $project->load(['plan', 'stakeholders', 'teamMembers', 'milestones']);
        $asOf = $this->statusDate();

        return response()->json([
            'data' => $this->projectArray($project) + [
                'objectives'       => $project->objectives,
                'scope_boundaries' => $project->scope_boundaries,
                'plan'             => $project->plan ? [
                    'version'          => $project->plan->version,
                    'cpi_threshold'    => (float) $project->plan->cpi_threshold,
                    'spi_threshold'    => (float) $project->plan->spi_threshold,
                    'budget_alert_pct' => (float) $project->plan->budget_alert_pct,
                ] : null,
                'stakeholders'     => $project->stakeholders->map(fn ($s) => [
                    'name' => $s->name, 'role' => $s->role, 'influence' => $s->influence, 'interest' => $s->interest,
                ])->values(),
                'team'             => $project->teamMembers->map(fn ($m) => [
                    'id' => $m->id, 'name' => $m->name, 'role' => $m->role, 'daily_rate' => (float) $m->daily_rate,
                ])->values(),
                'milestones'       => $this->milestoneStates($project, $asOf)->map(fn ($m) => [
                    'title' => $m->title, 'due_date' => $m->due_date->toDateString(), 'state' => $m->state,
                    'responsible' => $m->responsible?->name,
                ])->values(),
            ],
        ]);
    }

    /** Everything the dashboard shows, as JSON. */
    public function dashboard(Project $project): JsonResponse
    {
        $s = $this->buildSummary($project);
        $analysis = $s['analysis'];

        return response()->json([
            'project'     => $this->projectArray($project),
            'status_date' => $s['asOf']->toDateString(),
            'progress'    => $s['progress'],
            'budget'      => $s['budget'],
            'deadline'    => [
                'approved'         => $s['deadline']['approved']->toDateString(),
                'current'          => $s['deadline']['current']->toDateString(),
                'extension_days'   => $s['deadline']['extension'],
                'planned_finish'   => $analysis['planned_finish']->toDateString(),
                'projected_finish' => $analysis['projected_finish']->toDateString(),
                'slip_days'        => $analysis['deadline_slip_days'],
            ],
            'evm'         => $s['evm'],
            'warnings'    => $s['warnings'],
            'pending_change_requests' => $s['pendingChangeRequests']->map(fn (ChangeRequest $cr) => [
                'code' => $cr->code, 'title' => $cr->title,
                'cost_impact' => (float) $cr->cost_impact, 'schedule_impact_days' => $cr->schedule_impact_days,
            ])->values(),
            'delayed_activities' => $this->delaysArray($analysis['delays']),
            'unresolved_critical_defects' => $s['criticalDefects']->map(fn ($d) => [
                'code' => $d->code, 'title' => $d->title, 'status' => $d->status, 'assigned_to' => $d->assignee?->name,
            ])->values(),
            'consistency' => $s['consistency'],
        ]);
    }

    // =====================================================================
    // Schedule and EVM
    // =====================================================================

    private function delaysArray(array $delays): array
    {
        return collect($delays)->map(fn ($d) => [
            'code'                 => $d['activity']->code,
            'name'                 => $d['activity']->name,
            'delay_days'           => $d['delay_days'],
            'delayed_dependants'   => $d['impacted'],
        ])->values()->all();
    }

    public function schedule(Project $project): JsonResponse
    {
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);
        $activities = $project->activities()->with(['assignee', 'predecessors'])->orderBy('planned_start')->orderBy('code')->get();

        return response()->json([
            'status_date'        => $asOf->toDateString(),
            'deadline'           => $project->current_deadline->toDateString(),
            'planned_finish'     => $analysis['planned_finish']->toDateString(),
            'projected_finish'   => $analysis['projected_finish']->toDateString(),
            'deadline_slip_days' => $analysis['deadline_slip_days'],
            'critical_path'      => $analysis['critical_path'],
            'activities'         => $this->ganttRows($activities),
            'delays'             => $this->delaysArray($analysis['delays']),
            'resource_conflicts' => $this->resourceConflicts($activities),
        ]);
    }

    public function evm(Project $project): JsonResponse
    {
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);
        $evm = $this->computeEvm($project, $asOf);

        return response()->json([
            'evm'        => $evm,
            'thresholds' => $this->thresholds($project),
            'warnings'   => $this->evaluateWarnings($project, $evm, $analysis),
            'curve'      => $this->evmCurve($project, $asOf),
        ]);
    }

    // =====================================================================
    // Scope and change requests
    // =====================================================================

    public function requirements(Request $request, Project $project): JsonResponse
    {
        $requirements = $project->requirements()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->has('in_scope'), fn ($q) => $q->where('in_scope', $request->boolean('in_scope')))
            ->orderBy('code')->get();

        return response()->json(['data' => $requirements->map(fn ($r) => [
            'code' => $r->code, 'title' => $r->title, 'type' => $r->type, 'priority' => $r->priority,
            'status' => $r->status, 'in_scope' => (bool) $r->in_scope,
            'origin_change_request_id' => $r->origin_change_request_id,
        ])->values()]);
    }

    private function changeRequestArray(Project $project, ChangeRequest $cr): array
    {
        $impact = $this->crPreview($project, $cr);

        return [
            'id'                   => $cr->id,
            'code'                 => $cr->code,
            'title'                => $cr->title,
            'status'               => $cr->status,
            'justification'        => $cr->justification,
            'requested_by'         => $cr->requested_by,
            'cost_impact'          => (float) $cr->cost_impact,
            'schedule_impact_days' => $cr->schedule_impact_days,
            'requirements'         => $cr->requirements->map(fn ($r) => [
                'code' => $r->code, 'title' => $r->title, 'status' => $r->status, 'in_scope' => (bool) $r->in_scope,
            ])->values(),
            'impact'               => [
                'applied'         => $impact['applied'],
                'budget_before'   => $impact['budget_before'],
                'budget_after'    => $impact['budget_after'],
                'deadline_before' => $impact['deadline_before'],
                'deadline_after'  => $impact['deadline_after'],
            ],
            'decided_at'           => $cr->decided_at?->toIso8601String(),
        ];
    }

    public function changeRequests(Project $project): JsonResponse
    {
        $list = $project->changeRequests()->with('requirements')->orderBy('code')->get();

        return response()->json([
            'baseline' => [
                'approved_budget'   => (float) $project->approved_budget,
                'current_budget'    => (float) $project->current_budget,
                'approved_deadline' => $project->approved_deadline->toDateString(),
                'current_deadline'  => $project->current_deadline->toDateString(),
            ],
            'data'     => $list->map(fn ($cr) => $this->changeRequestArray($project, $cr))->values(),
        ]);
    }

    /** Before/after effect of one change request on scope, budget and deadline. */
    public function changeRequestImpact(ChangeRequest $changeRequest): JsonResponse
    {
        $changeRequest->load('requirements');

        return response()->json(['data' => $this->changeRequestArray($changeRequest->project, $changeRequest)]);
    }

    // =====================================================================
    // Quality and risk
    // =====================================================================

    public function defects(Request $request, Project $project): JsonResponse
    {
        $defects = $project->defects()->with(['assignee', 'requirement', 'activity'])
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->query('severity')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->boolean('unresolved'), fn ($q) => $q->unresolved())
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")->orderBy('code')->get();

        return response()->json(['data' => $defects->map(fn ($d) => [
            'code' => $d->code, 'title' => $d->title, 'severity' => $d->severity, 'status' => $d->status,
            'assigned_to' => $d->assignee?->name, 'requirement' => $d->requirement?->code, 'activity' => $d->activity?->code,
            'reported_on' => $d->reported_on->toDateString(), 'resolved_on' => $d->resolved_on?->toDateString(),
        ])->values()]);
    }

    public function risks(Project $project): JsonResponse
    {
        $risks = $project->risks()->with('owner')->ranked()->get();

        return response()->json(['data' => $risks->map(fn ($r, $i) => [
            'rank' => $i + 1, 'code' => $r->code, 'title' => $r->title, 'category' => $r->category,
            'likelihood' => $r->likelihood, 'impact' => $r->impact, 'score' => $r->score,
            'level' => $r->level, 'status' => $r->status, 'owner' => $r->owner?->name,
        ])->values()]);
    }
}
