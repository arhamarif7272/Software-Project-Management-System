<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityDependency;
use App\Models\Alert;
use App\Models\ChangeRequest;
use App\Models\ChecklistItem;
use App\Models\CostEntry;
use App\Models\Defect;
use App\Models\EvmSnapshot;
use App\Models\InspectionResult;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\QualityChecklist;
use App\Models\Requirement;
use App\Models\Risk;
use App\Models\RiskResponse;
use App\Models\Stakeholder;
use App\Models\TeamMember;
use App\Models\WbsItem;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * MainController: all six project-management modules.
 *
 *  1. Initiation & Integration   (projects, charter, plan, stakeholders, team, milestones, closure)
 *  2. Scope & WBS                (requirements, WBS, change requests)
 *  3. Schedule & Resources       (activities, dependencies, critical path, delay impact, Gantt data)
 *  4. Cost & EVM                 (cost entries, PV / EV / AC / CPI / SPI, forecasts, warnings)
 *  5. Quality & Risk             (defects, checklists, inspections, prioritization matrix, PDPC)
 *  6. Integration & Reporting    (dashboard, consistency checks, report)
 *
 * All durations are CALENDAR days. The "status date" (the date EVM and delay analysis are
 * measured at) is kept in the session and defaults to today, so a demo can be run at any date.
 */
class MainController extends Controller
{
    private const WBS_LEVELS = ['project' => 0, 'phase' => 1, 'deliverable' => 2, 'work_package' => 3];
    private const SCOPE_LOCKED_STATUSES = ['executing', 'monitoring', 'closing', 'closed'];
    private const UNRESOLVED_DEFECT_STATUSES = ['open', 'in_progress', 'reopened'];
    private const DEFECT_TRANSITIONS = [
        'open'        => ['in_progress', 'resolved'],
        'in_progress' => ['open', 'resolved'],
        'resolved'    => ['verified', 'closed', 'reopened'],
        'verified'    => ['closed', 'reopened'],
        'closed'      => ['reopened'],
        'reopened'    => ['in_progress', 'resolved'],
    ];

    // =====================================================================
    // SHARED HELPERS
    // =====================================================================

    protected function statusDate(): Carbon
    {
        $date = session('status_date');

        return $date ? Carbon::parse($date)->startOfDay() : Carbon::today();
    }

    public function setStatusDate(Request $request): RedirectResponse
    {
        $data = $request->validate(['status_date' => ['nullable', 'date']]);

        if (empty($data['status_date'])) {
            session()->forget('status_date');
            $message = 'Status date reset to today.';
        } else {
            session(['status_date' => $data['status_date']]);
            $message = 'Status date set to ' . $data['status_date'] . '.';
        }

        return back()->with('success', $message);
    }

    /** Whole days from $from to $to (positive when $to is later). */
    protected function days(Carbon $from, Carbon $to): int
    {
        return (int) round(($to->copy()->startOfDay()->timestamp - $from->copy()->startOfDay()->timestamp) / 86400);
    }

    protected function fail(string $message): RedirectResponse
    {
        return back()->withInput()->withErrors(['error' => $message]);
    }

    protected function pkr(float|int|string $value): string
    {
        return 'PKR ' . number_format((float) $value, 0);
    }

    /** Next sequential code for a project-scoped model, e.g. REQ-007. */
    protected function nextCode(string $modelClass, int $projectId, string $prefix): string
    {
        $max = $modelClass::where('project_id', $projectId)->pluck('code')
            ->map(fn ($code) => (int) preg_replace('/\D/', '', (string) $code))
            ->max() ?? 0;

        return sprintf('%s-%03d', $prefix, $max + 1);
    }

    protected function nextWbsCode(Project $project, ?WbsItem $parent): string
    {
        $max = WbsItem::where('project_id', $project->id)
            ->where('parent_id', $parent?->id)
            ->pluck('wbs_code')
            ->map(fn ($code) => (int) last(explode('.', (string) $code)))
            ->max() ?? 0;

        return $parent ? $parent->wbs_code . '.' . ($max + 1) : (string) ($max + 1);
    }

    protected function descendantIds(WbsItem $item): array
    {
        $ids = [];
        $frontier = [$item->id];

        while ($frontier) {
            $children = WbsItem::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    protected function thresholds(Project $project): array
    {
        $plan = $project->plan;

        return [
            'cpi'        => (float) ($plan?->cpi_threshold ?? 0.90),
            'spi'        => (float) ($plan?->spi_threshold ?? 0.90),
            'budget_pct' => (float) ($plan?->budget_alert_pct ?? 90),
        ];
    }

    // =====================================================================
    // MODULE 1: PROJECT INITIATION & INTEGRATION
    // =====================================================================

    public function projectsIndex(): View
    {
        $projects = Project::withCount(['activities', 'requirements', 'defects'])->latest()->get();

        return view('projects.index', compact('projects'));
    }

    public function projectCreate(): View
    {
        return view('projects.create');
    }

    public function projectStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'description'       => ['nullable', 'string'],
            'objectives'        => ['required', 'string'],
            'scope_boundaries'  => ['required', 'string'],
            'approved_budget'   => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'start_date'        => ['required', 'date'],
            'approved_deadline' => ['required', 'date', 'after:start_date'],
        ]);

        $project = DB::transaction(function () use ($data) {
            $project = Project::create($data + [
                'current_budget'   => $data['approved_budget'],
                'current_deadline' => $data['approved_deadline'],
                'status'           => 'initiation',
                'created_by'       => Auth::id(),
            ]);

            $project->plan()->create([]); // management plan with default thresholds

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', 'Project charter created.');
    }

    public function projectShow(Project $project): View
    {
        $project->load([
            'plan',
            'stakeholders',
            'teamMembers',
            'milestones' => fn ($q) => $q->orderBy('due_date'),
            'milestones.responsible',
        ]);

        $thresholds = $this->thresholds($project);
        $milestones = $this->milestoneStates($project, $this->statusDate());

        return view('projects.show', compact('project', 'thresholds', 'milestones'));
    }

    public function projectUpdate(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'description'       => ['nullable', 'string'],
            'objectives'        => ['required', 'string'],
            'scope_boundaries'  => ['required', 'string'],
            'approved_budget'   => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'start_date'        => ['required', 'date'],
            'approved_deadline' => ['required', 'date', 'after:start_date'],
        ]);

        if ($project->isClosed()) {
            return $this->fail('A closed project cannot be edited.');
        }

        $startDate = Carbon::parse($data['start_date'])->toDateString();
        $deadline = Carbon::parse($data['approved_deadline'])->toDateString();

        if ($project->charter_approved) {
            $baselineChanged = (float) $data['approved_budget'] !== (float) $project->approved_budget
                || $startDate !== $project->start_date->toDateString()
                || $deadline !== $project->approved_deadline->toDateString();

            if ($baselineChanged) {
                return $this->fail('Budget, start date and deadline are locked after charter approval. Raise a change request instead.');
            }

            unset($data['approved_budget'], $data['start_date'], $data['approved_deadline']);
        } else {
            $data['current_budget'] = $data['approved_budget'];
            $data['current_deadline'] = $data['approved_deadline'];
        }

        $project->update($data);
        $this->refreshSchedule($project->fresh());

        return back()->with('success', 'Project charter updated.');
    }

    public function projectDestroy(Project $project): RedirectResponse
    {
        if ($project->charter_approved) {
            return $this->fail('An approved project cannot be deleted. Close it instead.');
        }

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    public function charterApprove(Project $project): RedirectResponse
    {
        if ($project->charter_approved) {
            return $this->fail('The charter is already approved.');
        }

        if ($project->stakeholders()->count() === 0) {
            return $this->fail('Add at least one stakeholder before approving the charter.');
        }

        $project->update([
            'charter_approved'    => true,
            'charter_approved_at' => now(),
            'status'              => $project->status === 'initiation' ? 'planning' : $project->status,
        ]);

        return back()->with('success', 'Charter approved. Budget and deadline are now a locked baseline; use change requests to alter them.');
    }

    public function planUpdate(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'scope_statement'   => ['nullable', 'string'],
            'schedule_approach' => ['nullable', 'string'],
            'cost_approach'     => ['nullable', 'string'],
            'quality_approach'  => ['nullable', 'string'],
            'responsibilities'  => ['nullable', 'string'], // ADDED: Responsibilities
            'cpi_threshold'     => ['required', 'numeric', 'between:0.1,2'],
            'spi_threshold'     => ['required', 'numeric', 'between:0.1,2'],
            'budget_alert_pct'  => ['required', 'numeric', 'between:1,100'],
        ]);

        $plan = $project->plan ?? new ProjectPlan(['project_id' => $project->id]);
        $plan->fill($data);
        $plan->version = $plan->exists ? $plan->version + 1 : 1;
        $plan->save();

        return back()->with('success', "Project management plan saved (version {$plan->version}).");
    }

    public function statusUpdate(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['initiation', 'planning', 'executing', 'monitoring', 'closing'])],
        ]);

        if ($project->isClosed()) {
            return $this->fail('The project is already closed.');
        }

        if ($data['status'] !== 'initiation' && !$project->charter_approved) {
            return $this->fail('Approve the project charter before moving past initiation.');
        }

        if (in_array($data['status'], ['executing', 'monitoring'], true)
            && !$project->requirements()->inScope()->exists()) {
            return $this->fail('At least one approved requirement is needed before execution can start.');
        }

        $project->update(['status' => $data['status']]);

        return back()->with('success', 'Project status changed to ' . $data['status'] . '.');
    }

    public function projectClose(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'closure_summary'    => ['required', 'string', 'min:10'],
            'lessons_learned'    => ['nullable', 'string'],
            'closure_date'       => ['nullable', 'date'],
            'confirm_incomplete' => ['nullable', 'boolean'],
        ]);

        if ($project->isClosed()) {
            return $this->fail('The project is already closed.');
        }

        $blockers = [];
        $critical = $project->defects()->unresolved()->critical()->count();
        $pending = $project->changeRequests()->pending()->count();

        if ($critical > 0) {
            $blockers[] = "{$critical} unresolved critical defect(s)";
        }
        if ($pending > 0) {
            $blockers[] = "{$pending} pending change request(s)";
        }
        if ($blockers) {
            return $this->fail('The project cannot be closed with ' . implode(' and ', $blockers) . '.');
        }

        $incomplete = $project->activities()->where('status', '!=', 'completed')->count();
        if ($incomplete > 0 && !$request->boolean('confirm_incomplete')) {
            return $this->fail("{$incomplete} activities are not completed. Tick the confirmation box to close anyway.");
        }

        $project->update([
            'status'          => 'closed',
            'closure_date'    => $data['closure_date'] ?? $this->statusDate()->toDateString(),
            'closure_summary' => $data['closure_summary'],
            'lessons_learned' => $data['lessons_learned'] ?? null,
        ]);

        return back()->with('success', 'Project closed and closure record saved.');
    }

    // ---- Stakeholders ---------------------------------------------------

    public function stakeholderStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'role'         => ['nullable', 'string', 'max:100'],
            'organization' => ['nullable', 'string', 'max:150'],
            'influence'    => ['required', Rule::in(['low', 'medium', 'high'])],
            'interest'     => ['required', Rule::in(['low', 'medium', 'high'])],
            'contact'      => ['nullable', 'string', 'max:150'],
        ]);

        $project->stakeholders()->create($data);

        return back()->with('success', 'Stakeholder added.');
    }

    public function stakeholderDestroy(Stakeholder $stakeholder): RedirectResponse
    {
        $project = $stakeholder->project;

        if ($project->charter_approved && $project->stakeholders()->count() <= 1) {
            return $this->fail('An approved charter must keep at least one stakeholder.');
        }

        $stakeholder->delete();

        return back()->with('success', 'Stakeholder removed.');
    }

    // ---- Team / responsibilities ---------------------------------------

    public function teamStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'role'       => ['required', Rule::in(['project_manager', 'backend_developer', 'frontend_developer', 'database_developer', 'qa_engineer', 'other'])],
            'email'      => ['nullable', 'email', 'max:150'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $project->teamMembers()->create($data + ['is_active' => true]);

        return back()->with('success', 'Team member added.');
    }

    public function teamDestroy(TeamMember $member): RedirectResponse
    {
        $open = $member->activities()->where('status', '!=', 'completed')->count();
        $defects = $member->assignedDefects()->whereIn('status', self::UNRESOLVED_DEFECT_STATUSES)->count();

        if ($open > 0 || $defects > 0) {
            return $this->fail("{$member->name} still has {$open} open activity(ies) and {$defects} unresolved defect(s). Reassign them first.");
        }

        $member->delete();

        return back()->with('success', 'Team member removed.');
    }

    // ---- Milestones -----------------------------------------------------

    public function milestoneStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string'],
            'due_date'       => ['required', 'date',
                'after_or_equal:' . $project->start_date->toDateString(),
                'before_or_equal:' . $project->current_deadline->toDateString()],
            'responsible_id' => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $project->id)],
        ]);

        $project->milestones()->create($data + ['status' => 'pending']);

        return back()->with('success', 'Milestone added.');
    }

    public function milestoneUpdate(Request $request, Milestone $milestone): RedirectResponse
    {
        $project = $milestone->project;

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string'],
            'due_date'       => ['required', 'date',
                'after_or_equal:' . $project->start_date->toDateString(),
                'before_or_equal:' . $project->current_deadline->toDateString()],
            'status'         => ['required', Rule::in(['pending', 'achieved', 'missed'])],
            'completed_date' => ['nullable', 'date'],
            'responsible_id' => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $project->id)],
        ]);

        if ($data['status'] === 'achieved') {
            if ($milestone->activities()->where('status', '!=', 'completed')->exists()) {
                return $this->fail('This milestone still has activities that are not completed.');
            }
            $data['completed_date'] = $data['completed_date'] ?? $this->statusDate()->toDateString();
        } else {
            $data['completed_date'] = null;
        }

        $milestone->update($data);

        return back()->with('success', 'Milestone updated.');
    }

    public function milestoneDestroy(Milestone $milestone): RedirectResponse
    {
        $milestone->delete();

        return back()->with('success', 'Milestone deleted.');
    }

    protected function milestoneStates(Project $project, Carbon $asOf): Collection
    {
        return $project->milestones()->with(['responsible', 'activities'])->orderBy('due_date')->get()
            ->map(function (Milestone $milestone) use ($asOf) {
                $state = match (true) {
                    $milestone->status === 'achieved'                                    => 'achieved',
                    $milestone->due_date->lt($asOf)                                      => 'missed',
                    $milestone->activities->contains(fn ($a) => $a->status === 'delayed') => 'at_risk',
                    default                                                              => 'on_track',
                };
                $milestone->setAttribute('state', $state); // display only, never saved

                return $milestone;
            });
    }

    // =====================================================================
    // MODULE 2: SCOPE MANAGEMENT & WBS
    // =====================================================================

    public function requirementsIndex(Request $request, Project $project): View
    {
        $requirements = $project->requirements()
            ->with('originChangeRequest')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('code')
            ->get();

        $stats = $project->requirements()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $pendingChangeRequests = $project->changeRequests()->pending()->get(['id', 'code', 'title']);
        $scopeLocked = in_array($project->status, self::SCOPE_LOCKED_STATUSES, true);

        return view('requirements.index', compact('project', 'requirements', 'stats', 'pendingChangeRequests', 'scopeLocked'));
    }

    public function requirementStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title'             => ['required', 'string', 'max:200'],
            'description'       => ['nullable', 'string'],
            'type'              => ['required', Rule::in(['functional', 'non_functional', 'constraint'])],
            'priority'          => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'change_request_id' => ['nullable', Rule::exists('change_requests', 'id')->where('project_id', $project->id)],
        ]);

        $changeRequest = isset($data['change_request_id']) ? ChangeRequest::find($data['change_request_id']) : null;

        if ($changeRequest && !$changeRequest->isPending()) {
            return $this->fail('Requirements can only be attached to a pending change request.');
        }

        DB::transaction(function () use ($data, $project, $changeRequest) {
            // New requirements always start as "proposed" and outside the approved scope.
            $requirement = $project->requirements()->create([
                'code'                     => $this->nextCode(Requirement::class, $project->id, 'REQ'),
                'title'                    => $data['title'],
                'description'              => $data['description'] ?? null,
                'type'                     => $data['type'],
                'priority'                 => $data['priority'],
                'status'                   => 'proposed',
                'in_scope'                 => false,
                'origin_change_request_id' => $changeRequest?->id,
            ]);

            if ($changeRequest) {
                $changeRequest->requirements()->syncWithoutDetaching([$requirement->id]);
            }
        });

        return back()->with('success', 'Requirement added as "proposed". It is not part of the scope until approved.');
    }

    public function requirementUpdate(Request $request, Requirement $requirement): RedirectResponse
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'type'        => ['required', Rule::in(['functional', 'non_functional', 'constraint'])],
            'priority'    => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
        ]);

        // FIXED: Allow updating even if in scope, to fully satisfy the requirement "add, update, prioritize, and approve"
        $requirement->update($data);

        return back()->with('success', 'Requirement updated.');
    }

    public function requirementApprove(Requirement $requirement): RedirectResponse
    {
        $project = $requirement->project;

        if ($requirement->status === 'approved') {
            return $this->fail('This requirement is already approved.');
        }

        if ($requirement->origin_change_request_id) {
            $changeRequest = $requirement->originChangeRequest;

            if (!$changeRequest || !in_array($changeRequest->status, ['approved', 'implemented'], true)) {
                $code = $changeRequest?->code ?? 'its change request';

                return $this->fail("This requirement was proposed through {$code}. Approve the change request instead; that adds it to the scope and applies its cost and schedule impact.");
            }
        } elseif (in_array($project->status, self::SCOPE_LOCKED_STATUSES, true)) {
            return $this->fail('The scope baseline is locked during execution. Raise a change request to add this requirement.');
        }

        $requirement->update([
            'status'      => 'approved',
            'in_scope'    => true,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "{$requirement->code} approved and added to the project scope.");
    }

    public function requirementStatus(Request $request, Requirement $requirement): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['proposed', 'rejected', 'deferred'])],
        ]);

        if ($requirement->in_scope) {
            return $this->fail('An approved requirement can only be removed from scope through a change request.');
        }

        $requirement->update(['status' => $data['status'], 'in_scope' => false]);

        return back()->with('success', "{$requirement->code} marked as {$data['status']}.");
    }

    public function requirementDestroy(Requirement $requirement): RedirectResponse
    {
        if ($requirement->in_scope) {
            return $this->fail('An approved requirement cannot be deleted.');
        }

        if ($requirement->activities()->exists() || $requirement->wbsItems()->exists() || $requirement->defects()->exists()) {
            return $this->fail('This requirement is referenced by activities, WBS items or defects and cannot be deleted.');
        }

        $requirement->delete();

        return back()->with('success', 'Requirement deleted.');
    }

    // ---- WBS ------------------------------------------------------------

    public function wbsIndex(Project $project): View
    {
        $tree = WbsItem::where('project_id', $project->id)->roots()->with(['childrenRecursive', 'requirement'])->get();

        $linkedRequirementIds = WbsItem::where('project_id', $project->id)->whereNotNull('requirement_id')->pluck('requirement_id');
        $uncovered = $project->requirements()->inScope()->whereNotIn('id', $linkedRequirementIds)->orderBy('code')->get();
        $scopeRequirements = $project->requirements()->inScope()->orderBy('code')->get();
        $items = $project->wbsItems()->orderBy('wbs_code')->get(['id', 'wbs_code', 'name', 'level_type']);

        return view('wbs.index', compact('project', 'tree', 'uncovered', 'scopeRequirements', 'items'));
    }

    public function wbsStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'parent_id'      => ['nullable', Rule::exists('wbs_items', 'id')->where('project_id', $project->id)],
            'requirement_id' => ['nullable', Rule::exists('requirements', 'id')->where('project_id', $project->id)],
            'name'           => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string'],
            'level_type'     => ['required', Rule::in(array_keys(self::WBS_LEVELS))],
        ]);

        $parent = isset($data['parent_id']) ? WbsItem::find($data['parent_id']) : null;

        if ($parent && self::WBS_LEVELS[$data['level_type']] <= self::WBS_LEVELS[$parent->level_type]) {
            return $this->fail("A {$data['level_type']} cannot sit under a {$parent->level_type}. Levels must go project, phase, deliverable, work package.");
        }

        if (!$parent && $data['level_type'] === 'work_package') {
            return $this->fail('A work package must belong to a deliverable.');
        }

        if (isset($data['requirement_id'])) {
            $requirement = Requirement::find($data['requirement_id']);
            if (!$requirement->in_scope) {
                return $this->fail("Requirement {$requirement->code} is not in the approved scope and cannot be linked to the WBS.");
            }
        }

        $item = $project->wbsItems()->create([
            'parent_id'      => $parent?->id,
            'requirement_id' => $data['requirement_id'] ?? null,
            'wbs_code'       => $this->nextWbsCode($project, $parent),
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'level_type'     => $data['level_type'],
            'sort_order'     => $project->wbsItems()->where('parent_id', $parent?->id)->count(),
        ]);

        return back()->with('success', "WBS item {$item->wbs_code} created.");
    }

    /** Builds the hierarchy automatically: one deliverable per approved requirement, with standard work packages. */
    public function wbsGenerate(Project $project): RedirectResponse
    {
        $created = DB::transaction(function () use ($project) {
            $root = WbsItem::where('project_id', $project->id)->whereNull('parent_id')->where('level_type', 'project')->first()
                ?? WbsItem::create([
                    'project_id' => $project->id,
                    'wbs_code'   => $this->nextWbsCode($project, null),
                    'name'       => $project->name,
                    'level_type' => 'project',
                    'sort_order' => 0,
                ]);

            $linked = WbsItem::where('project_id', $project->id)->whereNotNull('requirement_id')->pluck('requirement_id');
            $requirements = $project->requirements()->inScope()->whereNotIn('id', $linked)->orderBy('code')->get();

            $count = 0;
            foreach ($requirements as $requirement) {
                $deliverable = WbsItem::create([
                    'project_id'     => $project->id,
                    'parent_id'      => $root->id,
                    'requirement_id' => $requirement->id,
                    'wbs_code'       => $this->nextWbsCode($project, $root),
                    'name'           => Str::limit($requirement->title, 190, ''),
                    'level_type'     => 'deliverable',
                    'sort_order'     => $count,
                ]);

                foreach (['Design', 'Development', 'Testing'] as $order => $package) {
                    WbsItem::create([
                        'project_id' => $project->id,
                        'parent_id'  => $deliverable->id,
                        'wbs_code'   => $this->nextWbsCode($project, $deliverable),
                        'name'       => Str::limit($package . ': ' . $requirement->title, 195, ''),
                        'level_type' => 'work_package',
                        'sort_order' => $order,
                    ]);
                }

                $count++;
            }

            return $count;
        });

        return back()->with('success', $created > 0
            ? "WBS generated: {$created} deliverable(s) with Design, Development and Testing work packages."
            : 'The WBS already covers every approved requirement.');
    }

    public function wbsDestroy(WbsItem $item): RedirectResponse
    {
        $ids = array_merge([$item->id], $this->descendantIds($item));

        if (Activity::whereIn('wbs_item_id', $ids)->exists()) {
            return $this->fail('This WBS item (or one below it) has activities assigned. Reassign or delete them first.');
        }

        $item->delete();

        return back()->with('success', 'WBS item and its children deleted.');
    }

    // ---- Change requests ----------------------------------------------

    public function changeRequestsIndex(Project $project): View
    {
        $changeRequests = $project->changeRequests()->with(['requirements', 'decider'])->orderByDesc('id')->get();

        $previews = $changeRequests->mapWithKeys(fn (ChangeRequest $cr) => [$cr->id => $this->crPreview($project, $cr)]);

        $candidates = $project->requirements()->where('in_scope', false)->whereIn('status', ['proposed', 'deferred', 'rejected'])->orderBy('code')->get();

        $applied = $changeRequests->whereIn('status', ['approved', 'implemented']);
        $totals = [
            'cost' => (float) $applied->sum('cost_impact'),
            'days' => (int) $applied->sum('schedule_impact_days'),
        ];

        return view('change-requests.index', compact('project', 'changeRequests', 'previews', 'candidates', 'totals'));
    }

    protected function crPreview(Project $project, ChangeRequest $cr): array
    {
        if ($cr->isApplied()) {
            return [
                'applied'         => true,
                'budget_before'   => (float) $cr->budget_before,
                'budget_after'    => (float) $cr->budget_after,
                'deadline_before' => $cr->deadline_before?->toDateString(),
                'deadline_after'  => $cr->deadline_after?->toDateString(),
            ];
        }

        $budget = (float) $project->current_budget;

        return [
            'applied'         => false,
            'budget_before'   => $budget,
            'budget_after'    => $budget + (float) $cr->cost_impact,
            'deadline_before' => $project->current_deadline->toDateString(),
            'deadline_after'  => $project->current_deadline->copy()->addDays((int) $cr->schedule_impact_days)->toDateString(),
        ];
    }

    public function changeRequestStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title'                => ['required', 'string', 'max:200'],
            'description'          => ['required', 'string'],
            'justification'        => ['required', 'string', 'min:10'],
            'requested_by'         => ['required', 'string', 'max:150'],
            'cost_impact'          => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'schedule_impact_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'requirement_ids'      => ['nullable', 'array'],
            'requirement_ids.*'    => [Rule::exists('requirements', 'id')->where('project_id', $project->id)],
            'new_requirements'     => ['nullable', 'string'],
        ]);

        if ($project->isClosed()) {
            return $this->fail('Change requests cannot be raised on a closed project.');
        }

        $changeRequest = DB::transaction(function () use ($data, $project) {
            $changeRequest = $project->changeRequests()->create([
                'code'                 => $this->nextCode(ChangeRequest::class, $project->id, 'CR'),
                'title'                => $data['title'],
                'description'          => $data['description'],
                'justification'        => $data['justification'],
                'requested_by'         => $data['requested_by'],
                'cost_impact'          => $data['cost_impact'],
                'schedule_impact_days' => $data['schedule_impact_days'],
                'status'               => 'pending',
            ]);

            $requirementIds = $data['requirement_ids'] ?? [];

            // One new requirement per non-empty line of the "new requirements" box.
            $lines = preg_split('/\r\n|\r|\n/', trim((string) ($data['new_requirements'] ?? ''))) ?: [];
            foreach (array_filter(array_map('trim', $lines)) as $title) {
                $requirement = $project->requirements()->create([
                    'code'                     => $this->nextCode(Requirement::class, $project->id, 'REQ'),
                    'title'                    => Str::limit($title, 195, ''),
                    'type'                     => 'functional',
                    'priority'                 => 'medium',
                    'status'                   => 'proposed',
                    'in_scope'                 => false,
                    'origin_change_request_id' => $changeRequest->id,
                ]);
                $requirementIds[] = $requirement->id;
            }

            $changeRequest->requirements()->sync(array_unique($requirementIds));

            return $changeRequest;
        });

        return back()->with('success', "{$changeRequest->code} recorded and waiting for approval. Nothing in the scope, budget or deadline changes until it is approved.");
    }

    /** Approving applies the change to scope, budget and deadline in one transaction. */
    public function changeRequestApprove(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $request->validate(['decision_notes' => ['nullable', 'string']]);

        if (!$changeRequest->isPending()) {
            return $this->fail("{$changeRequest->code} has already been decided.");
        }

        $summary = DB::transaction(function () use ($request, $changeRequest) {
            $project = Project::whereKey($changeRequest->project_id)->lockForUpdate()->first();

            $budgetBefore = (float) $project->current_budget;
            $deadlineBefore = $project->current_deadline->copy();
            $budgetAfter = round($budgetBefore + (float) $changeRequest->cost_impact, 2);
            $deadlineAfter = $deadlineBefore->copy()->addDays((int) $changeRequest->schedule_impact_days);

            // Budget and schedule.
            $project->update(['current_budget' => $budgetAfter, 'current_deadline' => $deadlineAfter]);

            // Scope: every requirement tied to this change request enters the approved scope.
            $requirementIds = $changeRequest->requirements()->pluck('requirements.id')
                ->merge($changeRequest->originatedRequirements()->pluck('id'))
                ->unique()->values();

            Requirement::whereIn('id', $requirementIds)->where('project_id', $project->id)->update([
                'status'      => 'approved',
                'in_scope'    => true,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Audit trail on the change request itself.
            $changeRequest->update([
                'status'          => 'approved',
                'decision_notes'  => $request->input('decision_notes'),
                'decided_by'      => Auth::id(),
                'decided_at'      => now(),
                'applied_at'      => now(),
                'budget_before'   => $budgetBefore,
                'budget_after'    => $budgetAfter,
                'deadline_before' => $deadlineBefore->toDateString(),
                'deadline_after'  => $deadlineAfter->toDateString(),
            ]);

            Alert::create([
                'project_id' => $project->id,
                'type'       => 'scope',
                'severity'   => 'info',
                'message'    => sprintf(
                    '%s applied: budget %s to %s, deadline %s to %s, %d requirement(s) added to scope.',
                    $changeRequest->code,
                    $this->pkr($budgetBefore),
                    $this->pkr($budgetAfter),
                    $deadlineBefore->toDateString(),
                    $deadlineAfter->toDateString(),
                    $requirementIds->count()
                ),
            ]);

            return [
                'code'         => $changeRequest->code,
                'budget'       => [$budgetBefore, $budgetAfter],
                'deadline'     => [$deadlineBefore->toDateString(), $deadlineAfter->toDateString()],
                'requirements' => $requirementIds->count(),
                'project'      => $project,
            ];
        });

        $this->refreshSchedule($summary['project']->fresh());

        return back()->with('success', sprintf(
            '%s approved. Budget: %s to %s. Deadline: %s to %s. %d requirement(s) added to the approved scope.',
            $summary['code'],
            $this->pkr($summary['budget'][0]),
            $this->pkr($summary['budget'][1]),
            $summary['deadline'][0],
            $summary['deadline'][1],
            $summary['requirements']
        ));
    }

    public function changeRequestReject(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $data = $request->validate(['decision_notes' => ['required', 'string', 'min:5']]);

        if (!$changeRequest->isPending()) {
            return $this->fail("{$changeRequest->code} has already been decided.");
        }

        DB::transaction(function () use ($data, $changeRequest) {
            $changeRequest->update([
                'status'         => 'rejected',
                'decision_notes' => $data['decision_notes'],
                'decided_by'     => Auth::id(),
                'decided_at'     => now(),
            ]);

            // Requirements that only existed because of this request are rejected with it.
            $changeRequest->originatedRequirements()->where('status', 'proposed')->update(['status' => 'rejected', 'in_scope' => false]);
        });

        return back()->with('success', "{$changeRequest->code} rejected. Scope, budget and deadline are unchanged.");
    }

    public function changeRequestDestroy(ChangeRequest $changeRequest): RedirectResponse
    {
        if (!$changeRequest->isPending()) {
            return $this->fail('Only pending change requests can be deleted. Decided ones are kept as the audit trail.');
        }

        DB::transaction(function () use ($changeRequest) {
            $changeRequest->originatedRequirements()->where('in_scope', false)->delete();
            $changeRequest->delete();
        });

        return back()->with('success', 'Change request deleted.');
    }

    // =====================================================================
    // MODULE 3: SCHEDULE & RESOURCES
    // =====================================================================

    public function activitiesIndex(Project $project): View
    {
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);

        $activities = $project->activities()->with(['assignee', 'wbsItem', 'requirement', 'milestone', 'predecessors'])
            ->orderBy('planned_start')->orderBy('code')->get();

        $members = $project->teamMembers()->where('is_active', true)->orderBy('name')->get();
        $workPackages = $project->wbsItems()->where('level_type', 'work_package')->orderBy('wbs_code')->get();
        $scopeRequirements = $project->requirements()->inScope()->orderBy('code')->get();
        $milestones = $project->milestones()->orderBy('due_date')->get();
        $conflicts = $this->resourceConflicts($activities);

        $plannedCost = (float) $activities->sum('estimated_cost');
        $workload = $members->map(fn (TeamMember $m) => [
            'member' => $m,
            'count'  => $activities->where('assigned_to', $m->id)->where('status', '!=', 'completed')->count(),
            'days'   => (int) $activities->where('assigned_to', $m->id)->where('status', '!=', 'completed')->sum('duration_days'),
        ]);

        return view('activities.index', compact(
            'project', 'asOf', 'analysis', 'activities', 'members', 'workPackages',
            'scopeRequirements', 'milestones', 'conflicts', 'plannedCost', 'workload'
        ));
    }

    protected function activityRules(Project $project, bool $withPredecessors = false): array
    {
        $rules = [
            'name'             => ['required', 'string', 'max:200'],
            'duration_days'    => ['required', 'integer', 'min:1', 'max:1000'],
            'assigned_to'      => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $project->id)],
            'wbs_item_id'      => ['nullable', Rule::exists('wbs_items', 'id')->where('project_id', $project->id)],
            'requirement_id'   => ['nullable', Rule::exists('requirements', 'id')->where('project_id', $project->id)],
            'milestone_id'     => ['nullable', Rule::exists('milestones', 'id')->where('project_id', $project->id)],
            'estimated_cost'   => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'percent_complete' => ['nullable', 'numeric', 'between:0,100'],
            'status'           => ['nullable', Rule::in(['not_started', 'in_progress', 'completed', 'on_hold'])],
            'actual_start'     => ['nullable', 'date'],
            'actual_end'       => ['nullable', 'date', 'after_or_equal:actual_start'],
        ];

        if ($withPredecessors) {
            $rules['predecessor_ids'] = ['nullable', 'array'];
            $rules['predecessor_ids.*'] = [Rule::exists('activities', 'id')->where('project_id', $project->id)];
        }

        return $rules;
    }

    /** Applies the cross-module business rules and returns the column payload for an activity. */
    protected function prepareActivityPayload(Project $project, array $data, ?Activity $existing): array
    {
        if ($project->isClosed()) {
            throw new DomainException('Activities cannot be changed on a closed project.');
        }

        $requirement = !empty($data['requirement_id']) ? Requirement::find($data['requirement_id']) : null;
        if ($requirement && !$requirement->in_scope) {
            throw new DomainException("Requirement {$requirement->code} is not in the approved scope, so no activity can be planned for it.");
        }

        $workPackage = !empty($data['wbs_item_id']) ? WbsItem::find($data['wbs_item_id']) : null;
        if ($workPackage && $workPackage->level_type !== 'work_package') {
            throw new DomainException('Activities can only be attached to WBS work packages.');
        }

        $member = !empty($data['assigned_to']) ? TeamMember::find($data['assigned_to']) : null;
        if ($member && !$member->is_active) {
            throw new DomainException("{$member->name} is not an active team member.");
        }

        // Cost estimate: explicit value, otherwise duration x the assignee's daily rate.
        $duration = (int) $data['duration_days'];
        $cost = $data['estimated_cost'] ?? null;
        if ($cost === null || $cost === '') {
            $cost = $member ? (float) $member->daily_rate * $duration : 0;
        }
        $cost = round((float) $cost, 2);

        // Budget guard: planned cost may never exceed the approved (current) budget.
        $otherCosts = (float) $project->activities()
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->sum('estimated_cost');
        $budget = (float) $project->current_budget;

        if ($otherCosts + $cost > $budget + 0.001) {
            throw new DomainException(sprintf(
                'Planned cost would reach %s, above the approved budget of %s. Reduce the estimate or raise a change request.',
                $this->pkr($otherCosts + $cost),
                $this->pkr($budget)
            ));
        }

        return [
            'name'           => $data['name'],
            'duration_days'  => $duration,
            'wbs_item_id'    => $workPackage?->id,
            'requirement_id' => $requirement?->id,
            'assigned_to'    => $member?->id,
            'milestone_id'   => $data['milestone_id'] ?? null,
            'estimated_cost' => $cost,
        ] + $this->normaliseProgress($data, $existing);
    }

    /** Keeps percent complete, status and actual dates consistent with each other. */
    protected function normaliseProgress(array $data, ?Activity $existing): array
    {
        $asOf = $this->statusDate();

        $percent = (float) ($data['percent_complete'] ?? $existing?->percent_complete ?? 0);
        $status = $data['status'] ?? (($existing && $existing->status !== 'delayed') ? $existing->status : null);
        $actualStart = !empty($data['actual_start']) ? Carbon::parse($data['actual_start']) : $existing?->actual_start;
        $actualEnd = !empty($data['actual_end']) ? Carbon::parse($data['actual_end']) : $existing?->actual_end;

        if ($status === 'completed') {
            $percent = 100.0;
        }

        if ($percent >= 100) {
            $status = 'completed';
            $percent = 100.0;
        } elseif ($status !== 'on_hold') {
            $status = ($percent > 0 || $status === 'in_progress') ? 'in_progress' : 'not_started';
        }

        if (in_array($status, ['in_progress', 'completed'], true) && !$actualStart) {
            $actualStart = $asOf->copy();
        }
        if ($status === 'not_started') {
            $actualStart = null;
        }

        if ($status === 'completed') {
            $actualEnd = $actualEnd ?? $asOf->copy();
            if ($actualStart && $actualEnd->lt($actualStart)) {
                throw new DomainException('The actual end date cannot be before the actual start date.');
            }
        } else {
            $actualEnd = null;
        }

        return [
            'percent_complete' => $percent,
            'status'           => $status,
            'actual_start'     => $actualStart?->toDateString(),
            'actual_end'       => $actualEnd?->toDateString(),
        ];
    }

    public function activityStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate($this->activityRules($project, true));

        try {
            $activity = DB::transaction(function () use ($data, $project) {
                $payload = $this->prepareActivityPayload($project, $data, null);

                $activity = $project->activities()->create(
                    $payload + ['code' => $this->nextCode(Activity::class, $project->id, 'A')]
                );

                foreach (array_unique($data['predecessor_ids'] ?? []) as $predecessorId) {
                    ActivityDependency::create([
                        'activity_id'     => $activity->id,
                        'predecessor_id'  => $predecessorId,
                        'dependency_type' => 'FS',
                        'lag_days'        => 0,
                    ]);
                }

                return $activity;
            });
        } catch (DomainException $e) {
            return $this->fail($e->getMessage());
        }

        $this->refreshSchedule($project);

        return back()->with('success', "Activity {$activity->code} added and the schedule recalculated.");
    }

    public function activityUpdate(Request $request, Activity $activity): RedirectResponse
    {
        $project = $activity->project;
        $data = $request->validate($this->activityRules($project));

        try {
            DB::transaction(function () use ($data, $project, $activity) {
                $activity->update($this->prepareActivityPayload($project, $data, $activity));
            });
        } catch (DomainException $e) {
            return $this->fail($e->getMessage());
        }

        $this->refreshSchedule($project);

        return back()->with('success', "Activity {$activity->code} updated and the schedule recalculated.");
    }

    public function activityDestroy(Activity $activity): RedirectResponse
    {
        $project = $activity->project;

        if ($activity->costEntries()->exists()) {
            return $this->fail("{$activity->code} has recorded costs and cannot be deleted.");
        }

        if ($activity->defects()->whereIn('status', self::UNRESOLVED_DEFECT_STATUSES)->exists()) {
            return $this->fail("{$activity->code} has unresolved defects and cannot be deleted.");
        }

        $activity->delete();
        $this->refreshSchedule($project);

        return back()->with('success', 'Activity deleted and the schedule recalculated.');
    }

    public function dependencyStore(Request $request, Activity $activity): RedirectResponse
    {
        $project = $activity->project;

        $data = $request->validate([
            'predecessor_id'  => ['required', Rule::exists('activities', 'id')->where('project_id', $project->id)],
            'dependency_type' => ['required', Rule::in(['FS', 'SS', 'FF', 'SF'])],
            'lag_days'        => ['nullable', 'integer', 'between:-365,365'],
        ]);

        if ((int) $data['predecessor_id'] === $activity->id) {
            return $this->fail('An activity cannot depend on itself.');
        }

        if (ActivityDependency::where('activity_id', $activity->id)->where('predecessor_id', $data['predecessor_id'])->exists()) {
            return $this->fail('That dependency already exists.');
        }

        try {
            DB::transaction(function () use ($data, $activity, $project) {
                ActivityDependency::create([
                    'activity_id'     => $activity->id,
                    'predecessor_id'  => $data['predecessor_id'],
                    'dependency_type' => $data['dependency_type'],
                    'lag_days'        => $data['lag_days'] ?? 0,
                ]);

                // Throws (and rolls back) if the new link creates a circular chain.
                $this->analyzeSchedule($project, $this->statusDate());
            });
        } catch (DomainException $e) {
            return $this->fail($e->getMessage());
        }

        $this->refreshSchedule($project);

        return back()->with('success', 'Dependency added and the schedule recalculated.');
    }

    public function dependencyDestroy(ActivityDependency $dependency): RedirectResponse
    {
        $project = $dependency->activity->project;
        $dependency->delete();
        $this->refreshSchedule($project);

        return back()->with('success', 'Dependency removed and the schedule recalculated.');
    }

    public function scheduleGantt(Project $project): View
    {
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);

        $activities = $project->activities()->with(['assignee', 'predecessors', 'milestone'])->orderBy('planned_start')->orderBy('code')->get();
        $milestones = $this->milestoneStates($project, $asOf);
        $conflicts = $this->resourceConflicts($activities);
        $gantt = $this->ganttRows($activities);

        return view('schedule.gantt', compact('project', 'asOf', 'analysis', 'activities', 'milestones', 'conflicts', 'gantt'));
    }

    /** JSON feed for the Gantt chart (frappe-gantt format plus extra fields). */
    public function scheduleJson(Project $project): JsonResponse
    {
        $this->refreshSchedule($project, $this->statusDate());
        $activities = $project->activities()->with(['assignee', 'predecessors'])->orderBy('planned_start')->get();

        return response()->json([
            'tasks'      => $this->ganttRows($activities),
            'milestones' => $this->milestoneStates($project, $this->statusDate())->map(fn (Milestone $m) => [
                'id'    => 'M' . $m->id,
                'name'  => $m->title,
                'date'  => $m->due_date->toDateString(),
                'state' => $m->state,
            ])->values(),
            'deadline'   => $project->current_deadline->toDateString(),
            'status_date' => $this->statusDate()->toDateString(),
        ]);
    }

    protected function ganttRows(Collection $activities): array
    {
        return $activities->map(function (Activity $a) {
            $class = match (true) {
                $a->status === 'completed' => 'bar-done',
                $a->status === 'delayed'   => 'bar-delayed',
                (bool) $a->is_critical     => 'bar-critical',
                default                    => 'bar-normal',
            };

            return [
                'id'           => 'A' . $a->id,
                'code'         => $a->code,
                'name'         => $a->code . ' ' . $a->name,
                'start'        => $a->planned_start?->toDateString(),
                'end'          => $a->planned_end?->toDateString(),
                'progress'     => (float) ($a->status === 'completed' ? 100 : $a->percent_complete),
                'dependencies' => $a->predecessors->map(fn ($p) => 'A' . $p->id)->implode(', '),
                'custom_class' => $class,
                'assignee'     => $a->assignee?->name,
                'status'       => $a->status,
                'delay_days'   => (int) $a->delay_days,
                'is_critical'  => (bool) $a->is_critical,
                'slack_days'   => $a->slack_days,
            ];
        })->values()->all();
    }

    /** People booked on overlapping planned activities. */
    protected function resourceConflicts(Collection $activities): array
    {
        $conflicts = [];

        $byMember = $activities->whereNotNull('assigned_to')->where('status', '!=', 'completed')->groupBy('assigned_to');

        foreach ($byMember as $list) {
            $sorted = $list->filter(fn ($a) => $a->planned_start && $a->planned_end)->sortBy('planned_start')->values();

            for ($i = 0; $i < $sorted->count(); $i++) {
                for ($j = $i + 1; $j < $sorted->count(); $j++) {
                    $first = $sorted[$i];
                    $second = $sorted[$j];

                    if ($second->planned_start->gt($first->planned_end)) {
                        break; // sorted by start date, so nothing later can overlap $first
                    }

                    $overlapEnd = $first->planned_end->lt($second->planned_end) ? $first->planned_end : $second->planned_end;

                    $conflicts[] = [
                        'member'  => $first->assignee?->name ?? 'Unknown',
                        'first'   => $first->code,
                        'second'  => $second->code,
                        'from'    => $second->planned_start->toDateString(),
                        'to'      => $overlapEnd->toDateString(),
                        'days'    => $this->days($second->planned_start, $overlapEnd) + 1,
                    ];
                }
            }
        }

        return $conflicts;
    }

    // ---- Scheduling engine (CPM + delay projection) ----------------------

    protected function earliestStart(string $type, int $duration, int $predStart, int $predEnd, int $lag): int
    {
        return match ($type) {
            'SS'    => $predStart + $lag,
            'FF'    => $predEnd + $lag - $duration,
            'SF'    => $predStart + $lag - $duration,
            default => $predEnd + $lag, // FS
        };
    }

    /** Kahn's algorithm. Throws when the dependencies contain a cycle. */
    protected function topologicalOrder(Collection $activities, array $preds): array
    {
        $inDegree = [];
        $successors = [];

        foreach ($activities as $id => $activity) {
            $inDegree[$id] = count($preds[$id] ?? []);
        }
        foreach ($preds as $id => $list) {
            foreach ($list as [$predecessorId]) {
                $successors[$predecessorId][] = $id;
            }
        }

        $queue = array_keys(array_filter($inDegree, fn ($degree) => $degree === 0));
        $order = [];

        while ($queue) {
            $id = array_shift($queue);
            $order[] = $id;

            foreach ($successors[$id] ?? [] as $next) {
                if (--$inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if (count($order) !== $activities->count()) {
            $stuck = collect($inDegree)->filter(fn ($degree) => $degree > 0)->keys()->map(fn ($id) => $activities[$id]->code)->implode(', ');

            throw new DomainException("Circular dependency detected between activities: {$stuck}. Remove one of the links.");
        }

        return $order;
    }

    /**
     * Forward pass, backward pass (critical path) and a projected schedule measured at $asOf.
     * Offsets are days from the project start date; ends are exclusive.
     */
    protected function analyzeSchedule(Project $project, Carbon $asOf): array
    {
        $start = $project->start_date->copy()->startOfDay();
        $activities = $project->activities()->with('predecessors')->get()->keyBy('id');

        if ($activities->isEmpty()) {
            return [
                'activities' => $activities, 'order' => [], 'es' => [], 'ef' => [], 'ls' => [], 'lf' => [],
                'p_start' => [], 'p_end' => [], 'delay' => [], 'delays' => [], 'critical_path' => [],
                'start' => $start, 'planned_finish' => $start->copy(), 'projected_finish' => $start->copy(),
                'planned_slip_days' => 0, 'deadline_slip_days' => 0,
            ];
        }

        $preds = [];
        $succs = [];
        foreach ($activities as $activity) {
            foreach ($activity->predecessors as $predecessor) {
                if (!$activities->has($predecessor->id)) {
                    continue;
                }
                $type = (string) $predecessor->pivot->dependency_type;
                $lag = (int) $predecessor->pivot->lag_days;

                $preds[$activity->id][] = [$predecessor->id, $type, $lag];
                $succs[$predecessor->id][] = [$activity->id, $type, $lag];
            }
        }

        $order = $this->topologicalOrder($activities, $preds);

        // ---- forward pass: earliest start / finish ----
        $es = [];
        $ef = [];
        foreach ($order as $id) {
            $duration = (int) $activities[$id]->duration_days;
            $earliest = 0;

            foreach ($preds[$id] ?? [] as [$predecessorId, $type, $lag]) {
                $earliest = max($earliest, $this->earliestStart($type, $duration, $es[$predecessorId], $ef[$predecessorId], $lag));
            }

            $es[$id] = $earliest;
            $ef[$id] = $earliest + $duration;
        }
        $finish = max($ef);

        // ---- backward pass: latest start / finish ----
        $ls = [];
        $lf = [];
        foreach (array_reverse($order) as $id) {
            $duration = (int) $activities[$id]->duration_days;
            $bound = $finish;

            foreach ($succs[$id] ?? [] as [$successorId, $type, $lag]) {
                $bound = min($bound, match ($type) {
                    'SS'    => $ls[$successorId] - $lag + $duration,
                    'FF'    => $lf[$successorId] - $lag,
                    'SF'    => $lf[$successorId] - $lag + $duration,
                    default => $ls[$successorId] - $lag, // FS
                });
            }

            $lf[$id] = $bound;
            $ls[$id] = $bound - $duration;
        }

        // ---- projected schedule: actual progress at the status date pushes dependants out ----
        $today = $this->days($start, $asOf);
        $pStart = [];
        $pEnd = [];

        foreach ($order as $id) {
            $activity = $activities[$id];
            $duration = (int) $activity->duration_days;
            $done = $activity->status === 'completed' || (float) $activity->percent_complete >= 100;

            if ($done) {
                $s = $activity->actual_start ? $this->days($start, $activity->actual_start) : $es[$id];
                $e = $activity->actual_end ? $this->days($start, $activity->actual_end) + 1 : $s + $duration;
            } else {
                $dependencyStart = 0;
                foreach ($preds[$id] ?? [] as [$predecessorId, $type, $lag]) {
                    $dependencyStart = max($dependencyStart, $this->earliestStart($type, $duration, $pStart[$predecessorId], $pEnd[$predecessorId], $lag));
                }

                if ($activity->actual_start) {
                    $s = $this->days($start, $activity->actual_start);
                    $remaining = (int) ceil($duration * (100 - (float) $activity->percent_complete) / 100);
                    $e = max($s + 1, $today + $remaining);
                } else {
                    // Not started yet: it cannot start in the past.
                    $s = max($dependencyStart, $today);
                    $e = $s + $duration;
                }
            }

            $pStart[$id] = $s;
            $pEnd[$id] = $e;
        }

        $delay = [];
        foreach ($order as $id) {
            $delay[$id] = max(0, $pEnd[$id] - $ef[$id]);
        }

        // ---- who is held up by whom ----
        $delays = [];
        foreach ($order as $id) {
            if ($delay[$id] <= 0) {
                continue;
            }

            $impacted = [];
            $seen = [$id => true];
            $queue = [$id];

            while ($queue) {
                $current = array_shift($queue);
                foreach ($succs[$current] ?? [] as [$successorId]) {
                    if (isset($seen[$successorId])) {
                        continue;
                    }
                    $seen[$successorId] = true;
                    $queue[] = $successorId;

                    if ($delay[$successorId] > 0) {
                        $impacted[] = [
                            'code'       => $activities[$successorId]->code,
                            'name'       => $activities[$successorId]->name,
                            'delay_days' => $delay[$successorId],
                        ];
                    }
                }
            }

            $delays[] = ['activity' => $activities[$id], 'delay_days' => $delay[$id], 'impacted' => $impacted];
        }
        usort($delays, fn ($a, $b) => $b['delay_days'] <=> $a['delay_days']);

        $plannedFinish = $start->copy()->addDays($finish - 1);
        $projectedFinish = $start->copy()->addDays(max($pEnd) - 1);

        return [
            'activities'         => $activities,
            'order'              => $order,
            'es' => $es, 'ef' => $ef, 'ls' => $ls, 'lf' => $lf,
            'p_start'            => $pStart,
            'p_end'              => $pEnd,
            'delay'              => $delay,
            'delays'             => $delays,
            'critical_path'      => collect($order)->filter(fn ($id) => $ls[$id] - $es[$id] === 0)->map(fn ($id) => $activities[$id]->code)->values()->all(),
            'start'              => $start,
            'planned_finish'     => $plannedFinish,
            'projected_finish'   => $projectedFinish,
            'planned_slip_days'  => $this->days($project->current_deadline, $plannedFinish),
            'deadline_slip_days' => $this->days($project->current_deadline, $projectedFinish),
        ];
    }

    /** Runs the analysis and stores planned dates, slack, critical flag, delay and status on each activity. */
    protected function refreshSchedule(Project $project, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? $this->statusDate();
        $analysis = $this->analyzeSchedule($project, $asOf);
        $start = $analysis['start'];

        DB::transaction(function () use ($analysis, $start) {
            foreach ($analysis['activities'] as $id => $activity) {
                $delay = $analysis['delay'][$id];
                $status = $activity->status;

                if (!in_array($status, ['completed', 'on_hold'], true)) {
                    $base = ((float) $activity->percent_complete > 0 || $activity->actual_start) ? 'in_progress' : 'not_started';
                    $status = $delay > 0 ? 'delayed' : $base;
                }

                $activity->forceFill([
                    'planned_start' => $start->copy()->addDays($analysis['es'][$id])->toDateString(),
                    'planned_end'   => $start->copy()->addDays($analysis['ef'][$id] - 1)->toDateString(),
                    'late_start'    => $start->copy()->addDays($analysis['ls'][$id])->toDateString(),
                    'late_end'      => $start->copy()->addDays($analysis['lf'][$id] - 1)->toDateString(),
                    'slack_days'    => $analysis['ls'][$id] - $analysis['es'][$id],
                    'is_critical'   => ($analysis['ls'][$id] - $analysis['es'][$id]) === 0,
                    'delay_days'    => $delay,
                    'status'        => $status,
                ])->save();
            }
        });

        return $analysis;
    }

    // =====================================================================
    // MODULE 4: COST MANAGEMENT & EARNED VALUE
    // =====================================================================

    public function costsIndex(Project $project): View
    {
        $asOf = $this->statusDate();
        $this->refreshSchedule($project, $asOf);

        $activities = $project->activities()->with('assignee')->orderBy('code')->get();
        $actualByActivity = $project->costEntries()->selectRaw('activity_id, SUM(amount) as total')->groupBy('activity_id')->pluck('total', 'activity_id');

        $rows = $activities->map(function (Activity $a) use ($actualByActivity) {
            $estimated = (float) $a->estimated_cost;
            $actual = (float) ($actualByActivity[$a->id] ?? 0);

            return ['activity' => $a, 'estimated' => $estimated, 'actual' => $actual, 'variance' => $estimated - $actual];
        });

        $entries = $project->costEntries()->with('activity')->orderByDesc('entry_date')->orderByDesc('id')->limit(200)->get();
        $byCategory = $project->costEntries()->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category');

        $totals = [
            'planned'        => (float) $activities->sum('estimated_cost'),
            'actual'         => (float) $project->costEntries()->sum('amount'),
            'approved'       => (float) $project->approved_budget,
            'current_budget' => (float) $project->current_budget,
            'unallocated'    => (float) $project->costEntries()->whereNull('activity_id')->sum('amount'),
        ];
        $totals['remaining'] = $totals['current_budget'] - $totals['actual'];
        $totals['unplanned'] = $totals['current_budget'] - $totals['planned'];

        return view('costs.index', compact('project', 'asOf', 'rows', 'entries', 'byCategory', 'totals', 'activities'));
    }

    public function costStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'activity_id' => ['nullable', Rule::exists('activities', 'id')->where('project_id', $project->id)],
            'entry_date'  => ['required', 'date', 'after_or_equal:' . $project->start_date->toDateString()],
            'category'    => ['required', Rule::in(['labor', 'infrastructure', 'license', 'testing', 'other'])],
            'description' => ['nullable', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'gt:0', 'max:999999999999'],
        ]);

        if ($project->isClosed()) {
            return $this->fail('Costs cannot be recorded on a closed project.');
        }

        $project->costEntries()->create($data);

        // Immediate feedback when this expenditure pushes the project over a threshold.
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);
        $evm = $this->computeEvm($project, $asOf);
        $warnings = collect($this->evaluateWarnings($project, $evm, $analysis))
            ->filter(fn ($w) => in_array($w['type'], ['cost_overrun', 'budget_threshold'], true))
            ->pluck('message')->all();

        $redirect = back()->with('success', 'Actual cost of ' . $this->pkr($data['amount']) . ' recorded.');

        return $warnings ? $redirect->with('warning', implode(' ', $warnings)) : $redirect;
    }

    public function costDestroy(CostEntry $entry): RedirectResponse
    {
        if ($entry->project->isClosed()) {
            return $this->fail('Costs cannot be changed on a closed project.');
        }

        $entry->delete();

        return back()->with('success', 'Cost entry deleted.');
    }

    public function evmIndex(Project $project): View
    {
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);

        $evm = $this->computeEvm($project, $asOf);
        $warnings = $this->evaluateWarnings($project, $evm, $analysis);
        $snapshots = $project->evmSnapshots()->orderBy('status_date')->get();
        $curve = $this->evmCurve($project, $asOf);
        $thresholds = $this->thresholds($project);
        $unresolvedAlerts = $project->alerts()->where('is_resolved', false)->latest('id')->limit(20)->get();

        return view('evm.index', compact('project', 'asOf', 'evm', 'warnings', 'snapshots', 'curve', 'thresholds', 'unresolvedAlerts'));
    }

    /** Saves the current indicators as a history point and stores the warnings as alerts. */
    public function evmSnapshot(Project $project): RedirectResponse
    {
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);
        $evm = $this->computeEvm($project, $asOf);

        EvmSnapshot::updateOrCreate(
            ['project_id' => $project->id, 'status_date' => $asOf->toDateString()],
            Arr::only($evm, ['bac', 'pv', 'ev', 'ac', 'cv', 'sv', 'cpi', 'spi', 'eac', 'etc', 'vac'])
        );

        $warnings = $this->evaluateWarnings($project, $evm, $analysis);
        $this->persistAlerts($project, $warnings);

        return back()->with('success', 'EVM snapshot saved for ' . $asOf->toDateString() . ' with ' . count($warnings) . ' alert(s) recorded.');
    }

    protected function plannedValueAt(Collection $activities, Carbon $date): float
    {
        $pv = 0.0;

        foreach ($activities as $a) {
            if (!$a->planned_start || !$a->planned_end) {
                continue;
            }

            $duration = max(1, (int) $a->duration_days);

            if ($date->lt($a->planned_start)) {
                $plannedFraction = 0.0;
            } elseif ($date->gte($a->planned_end)) {
                $plannedFraction = 1.0;
            } else {
                $plannedFraction = ($this->days($a->planned_start, $date) + 1) / $duration;
            }

            $pv += (float) $a->estimated_cost * min(1.0, $plannedFraction);
        }

        return $pv;
    }

    /**
     * PV = budgeted cost of work scheduled, EV = budgeted cost of work performed, AC = actual cost.
     * CPI = EV / AC, SPI = EV / PV, EAC = BAC / CPI.
     */
    protected function computeEvm(Project $project, Carbon $asOf): array
    {
        $activities = $project->activities()->get();
        $thresholds = $this->thresholds($project);

        $bac = (float) $activities->sum('estimated_cost');
        $pv = $this->plannedValueAt($activities, $asOf);

        $ev = 0.0;
        foreach ($activities as $a) {
            $percent = $a->status === 'completed' ? 100.0 : (float) $a->percent_complete;
            $ev += (float) $a->estimated_cost * $percent / 100;
        }

        $ac = (float) $project->costEntries()->whereDate('entry_date', '<=', $asOf->toDateString())->sum('amount');

        $cpi = $ac > 0 ? $ev / $ac : null;
        $spi = $pv > 0 ? $ev / $pv : null;
        $eac = ($cpi !== null && $cpi > 0) ? $bac / $cpi : null;

        $costStatus = match (true) {
            $cpi === null                 => 'No cost data yet',
            $cpi >= 1                     => 'On or under budget',
            $cpi >= $thresholds['cpi']    => 'Slightly over budget',
            default                       => 'Over budget: action required',
        };
        $scheduleStatus = match (true) {
            $spi === null                 => 'Not started',
            $spi >= 1                     => 'On or ahead of schedule',
            $spi >= $thresholds['spi']    => 'Slightly behind schedule',
            default                       => 'Behind schedule: action required',
        };

        return [
            'status_date'        => $asOf->toDateString(),
            'bac'                => round($bac, 2),
            'pv'                 => round($pv, 2),
            'ev'                 => round($ev, 2),
            'ac'                 => round($ac, 2),
            'cv'                 => round($ev - $ac, 2),
            'sv'                 => round($ev - $pv, 2),
            'cpi'                => $cpi !== null ? round($cpi, 4) : null,
            'spi'                => $spi !== null ? round($spi, 4) : null,
            'eac'                => $eac !== null ? round($eac, 2) : null,
            'etc'                => $eac !== null ? round($eac - $ac, 2) : null,
            'vac'                => $eac !== null ? round($bac - $eac, 2) : null,
            'percent_complete'   => $bac > 0 ? round($ev / $bac * 100, 1) : 0.0,
            'percent_planned'    => $bac > 0 ? round($pv / $bac * 100, 1) : 0.0,
            'percent_spent'      => $bac > 0 ? round($ac / $bac * 100, 1) : 0.0,
            'cost_status'        => $costStatus,
            'schedule_status'    => $scheduleStatus,
        ];
    }

    /** Weekly planned-value curve and cumulative actual cost, for the S-curve chart. */
    protected function evmCurve(Project $project, Carbon $asOf): array
    {
        $activities = $project->activities()->get();
        $entries = $project->costEntries()->get(['entry_date', 'amount']);
        $totalDays = $this->days($project->start_date, $project->current_deadline);
        $weeks = (int) ceil(($totalDays + 1) / 7);

        $labels = [];
        $pv = [];
        $ac = [];

        for ($week = 0; $week <= $weeks; $week++) {
            $date = $project->start_date->copy()->addDays(min($week * 7, $totalDays));

            $labels[] = $date->toDateString();
            $pv[] = round($this->plannedValueAt($activities, $date), 2);
            $ac[] = $date->lte($asOf)
                ? round((float) $entries->filter(fn ($e) => $e->entry_date->lte($date))->sum('amount'), 2)
                : null;
        }

        return ['labels' => $labels, 'pv' => $pv, 'ac' => $ac];
    }

    /** Threshold checks. Returns a list of ['type', 'severity', 'message']. */
    protected function evaluateWarnings(Project $project, array $evm, array $analysis): array
    {
        $thresholds = $this->thresholds($project);
        $warnings = [];
        $budget = (float) $project->current_budget;

        if ($evm['cpi'] !== null && $evm['cpi'] < $thresholds['cpi']) {
            $warnings[] = [
                'type'     => 'cost_overrun',
                'severity' => $evm['cpi'] < $thresholds['cpi'] - 0.10 ? 'critical' : 'warning',
                'message'  => sprintf('Cost overrun: CPI is %.2f, below the %.2f threshold. Forecast at completion is %s against a budget of %s.',
                    $evm['cpi'], $thresholds['cpi'], $evm['eac'] !== null ? $this->pkr($evm['eac']) : 'n/a', $this->pkr($budget)),
            ];
        }

        if ($evm['spi'] !== null && $evm['spi'] < $thresholds['spi']) {
            $warnings[] = [
                'type'     => 'schedule_delay',
                'severity' => $evm['spi'] < $thresholds['spi'] - 0.10 ? 'critical' : 'warning',
                'message'  => sprintf('Schedule deviation: SPI is %.2f, below the %.2f threshold. Only %s of the planned value has been earned.',
                    $evm['spi'], $thresholds['spi'], $this->pkr($evm['ev'])),
            ];
        }

        if ($budget > 0 && $evm['ac'] > $budget) {
            $warnings[] = [
                'type'     => 'cost_overrun',
                'severity' => 'critical',
                'message'  => sprintf('Actual cost %s has exceeded the approved budget of %s.', $this->pkr($evm['ac']), $this->pkr($budget)),
            ];
        } elseif ($budget > 0 && $evm['ac'] / $budget * 100 >= $thresholds['budget_pct']) {
            $warnings[] = [
                'type'     => 'budget_threshold',
                'severity' => 'warning',
                'message'  => sprintf('%.1f%% of the budget is spent (alert level %.0f%%).', $evm['ac'] / $budget * 100, $thresholds['budget_pct']),
            ];
        }

        if ($evm['bac'] > $budget + 0.001) {
            $warnings[] = [
                'type'     => 'cost_overrun',
                'severity' => 'critical',
                'message'  => sprintf('Planned activity costs (%s) exceed the approved budget (%s).', $this->pkr($evm['bac']), $this->pkr($budget)),
            ];
        }

        if ($evm['eac'] !== null && $evm['eac'] > $budget + 0.001 && $evm['bac'] <= $budget + 0.001) {
            $warnings[] = [
                'type'     => 'cost_overrun',
                'severity' => 'warning',
                'message'  => sprintf('Forecast cost at completion (%s) is above the approved budget (%s) by %s.',
                    $this->pkr($evm['eac']), $this->pkr($budget), $this->pkr($evm['eac'] - $budget)),
            ];
        }

        if (($analysis['deadline_slip_days'] ?? 0) > 0) {
            $warnings[] = [
                'type'     => 'deadline_risk',
                'severity' => 'critical',
                'message'  => sprintf('Deadline at risk: the projected finish %s is %d day(s) after the deadline %s.',
                    $analysis['projected_finish']->toDateString(), $analysis['deadline_slip_days'], $project->current_deadline->toDateString()),
            ];
        } elseif (($analysis['planned_slip_days'] ?? 0) > 0) {
            $warnings[] = [
                'type'     => 'deadline_risk',
                'severity' => 'critical',
                'message'  => sprintf('The planned schedule finishes %d day(s) after the deadline %s.', $analysis['planned_slip_days'], $project->current_deadline->toDateString()),
            ];
        }

        $delayed = count($analysis['delays'] ?? []);
        if ($delayed > 0) {
            $warnings[] = [
                'type'     => 'schedule_delay',
                'severity' => 'warning',
                'message'  => "{$delayed} activity(ies) are delayed and are pushing their dependants out.",
            ];
        }

        $critical = $project->defects()->unresolved()->critical()->count();
        if ($critical > 0) {
            $warnings[] = [
                'type'     => 'critical_defect',
                'severity' => 'critical',
                'message'  => "{$critical} critical defect(s) are unresolved.",
            ];
        }

        $pending = $project->changeRequests()->pending()->count();
        if ($pending > 0) {
            $warnings[] = [
                'type'     => 'scope',
                'severity' => 'info',
                'message'  => "{$pending} change request(s) are waiting for a decision.",
            ];
        }

        return $warnings;
    }

    protected function persistAlerts(Project $project, array $warnings): void
    {
        DB::transaction(function () use ($project, $warnings) {
            $project->alerts()->where('is_resolved', false)->update(['is_resolved' => true, 'resolved_at' => now()]);

            foreach ($warnings as $warning) {
                $project->alerts()->create($warning + ['is_resolved' => false]);
            }
        });
    }

    // =====================================================================
    // MODULE 5: QUALITY MANAGEMENT & RISK CONTROL
    // =====================================================================

    public function defectsIndex(Request $request, Project $project): View
    {
        $defects = $project->defects()->with(['assignee', 'reporter', 'requirement', 'activity'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->input('severity')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->input('assigned_to')))
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('reported_on')
            ->get();

        $stats = [
            'total'      => $project->defects()->count(),
            'unresolved' => $project->defects()->unresolved()->count(),
            'critical'   => $project->defects()->unresolved()->critical()->count(),
            'closed'     => $project->defects()->whereIn('status', ['verified', 'closed'])->count(),
        ];

        $members = $project->teamMembers()->orderBy('name')->get();
        $requirements = $project->requirements()->orderBy('code')->get(['id', 'code', 'title']);
        $activities = $project->activities()->orderBy('code')->get(['id', 'code', 'name']);
        $transitions = self::DEFECT_TRANSITIONS;

        return view('defects.index', compact('project', 'defects', 'stats', 'members', 'requirements', 'activities', 'transitions'));
    }

    protected function defectRules(Project $project): array
    {
        return [
            'title'          => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string'],
            'severity'       => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'requirement_id' => ['nullable', Rule::exists('requirements', 'id')->where('project_id', $project->id)],
            'activity_id'    => ['nullable', Rule::exists('activities', 'id')->where('project_id', $project->id)],
            'assigned_to'    => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $project->id)],
            'reported_by'    => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $project->id)],
            'reported_on'    => ['required', 'date'],
        ];
    }

    public function defectStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate($this->defectRules($project));

        $defect = $project->defects()->create($data + [
            'code'   => $this->nextCode(Defect::class, $project->id, 'DEF'),
            'status' => 'open',
        ]);

        $redirect = back()->with('success', "Defect {$defect->code} logged.");

        return $defect->severity === 'critical'
            ? $redirect->with('warning', "{$defect->code} is critical. It will appear as a warning on the dashboard until it is resolved.")
            : $redirect;
    }

    public function defectUpdate(Request $request, Defect $defect): RedirectResponse
    {
        $project = $defect->project;

        $data = $request->validate($this->defectRules($project) + [
            'status'           => ['required', Rule::in(array_keys(self::DEFECT_TRANSITIONS))],
            'resolution_notes' => ['nullable', 'string'],
            'resolved_on'      => ['nullable', 'date'],
        ]);

        if ($data['status'] !== $defect->status
            && !in_array($data['status'], self::DEFECT_TRANSITIONS[$defect->status] ?? [], true)) {
            return $this->fail("A defect cannot move from {$defect->status} to {$data['status']}.");
        }

        $assignee = $data['assigned_to'] ?? $defect->assigned_to;
        if ($data['status'] === 'in_progress' && !$assignee) {
            return $this->fail('Assign the defect to someone before work starts on it.');
        }

        if (in_array($data['status'], ['resolved', 'verified', 'closed'], true)) {
            $notes = $data['resolution_notes'] ?? $defect->resolution_notes;
            if (blank($notes)) {
                return $this->fail('Resolution notes are required before a defect is marked resolved, verified or closed.');
            }

            $resolvedOn = Carbon::parse($data['resolved_on'] ?? $defect->resolved_on ?? $this->statusDate());
            if ($resolvedOn->lt(Carbon::parse($data['reported_on']))) {
                return $this->fail('A defect cannot be resolved before it was reported.');
            }
            $data['resolved_on'] = $resolvedOn->toDateString();
        } else {
            $data['resolved_on'] = null;
        }

        $defect->update($data);

        return back()->with('success', "{$defect->code} updated.");
    }

    public function defectDestroy(Defect $defect): RedirectResponse
    {
        if ($defect->inspectionResults()->exists()) {
            return $this->fail('This defect was raised by an inspection result and is kept for traceability.');
        }

        $defect->delete();

        return back()->with('success', 'Defect deleted.');
    }

    // ---- Quality checklists & inspections ------------------------------

    public function qualityIndex(Project $project): View
    {
        $checklists = $project->qualityChecklists()->with(['wbsItem', 'items.latestResult.inspector'])->orderBy('name')->get();

        $checklists->each(function (QualityChecklist $checklist) {
            $results = $checklist->items->map(fn ($item) => $item->latestResult?->result)->filter();
            $passed = $results->filter(fn ($r) => $r === 'pass')->count();
            $applicable = $results->filter(fn ($r) => $r !== 'not_applicable')->count();

            $checklist->setAttribute('pass_rate', $applicable > 0 ? round($passed / $applicable * 100, 1) : null);
            $checklist->setAttribute('inspected_items', $results->count());
        });

        $deliverables = $project->wbsItems()->whereIn('level_type', ['deliverable', 'work_package'])->orderBy('wbs_code')->get(['id', 'wbs_code', 'name']);
        $members = $project->teamMembers()->orderBy('name')->get();

        return view('quality.index', compact('project', 'checklists', 'deliverables', 'members'));
    }

    public function checklistStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'wbs_item_id' => ['nullable', Rule::exists('wbs_items', 'id')->where('project_id', $project->id)],
            'items'       => ['nullable', 'string'],
        ]);

        $checklist = DB::transaction(function () use ($data, $project) {
            $checklist = $project->qualityChecklists()->create([
                'name'        => $data['name'],
                'wbs_item_id' => $data['wbs_item_id'] ?? null,
            ]);

            // One checklist item per non-empty line.
            $lines = preg_split('/\r\n|\r|\n/', trim((string) ($data['items'] ?? ''))) ?: [];
            foreach (array_values(array_filter(array_map('trim', $lines))) as $order => $line) {
                $checklist->items()->create(['description' => Str::limit($line, 295, ''), 'sort_order' => $order]);
            }

            return $checklist;
        });

        return back()->with('success', "Checklist \"{$checklist->name}\" created with {$checklist->items()->count()} item(s).");
    }

    public function checklistDestroy(QualityChecklist $checklist): RedirectResponse
    {
        $checklist->delete();

        return back()->with('success', 'Checklist deleted.');
    }

    public function checklistItemStore(Request $request, QualityChecklist $checklist): RedirectResponse
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:300']]);

        $checklist->items()->create([
            'description' => $data['description'],
            'sort_order'  => (int) $checklist->items()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Checklist item added.');
    }

    /** Records an inspection/test result; a failed check can raise a linked defect. */
    public function inspectionStore(Request $request, ChecklistItem $item): RedirectResponse
    {
        $checklist = $item->checklist;
        $project = $checklist->project;

        $data = $request->validate([
            'inspection_type' => ['required', Rule::in(['inspection', 'unit_test', 'integration_test', 'system_test', 'uat'])],
            'result'          => ['required', Rule::in(['pass', 'fail', 'not_applicable'])],
            'inspected_on'    => ['required', 'date'],
            'inspected_by'    => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $project->id)],
            'notes'           => ['nullable', 'string'],
            'raise_defect'    => ['nullable', 'boolean'],
            'defect_severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
        ]);

        if ($data['result'] === 'fail' && blank($data['notes'] ?? null)) {
            return $this->fail('Describe what failed in the notes.');
        }

        if ($request->boolean('raise_defect') && ($data['result'] !== 'fail' || empty($data['defect_severity']))) {
            return $this->fail('A defect can only be raised for a failed check, and it needs a severity.');
        }

        $defect = DB::transaction(function () use ($data, $item, $checklist, $project, $request) {
            $defect = null;

            if ($request->boolean('raise_defect')) {
                $defect = $project->defects()->create([
                    'code'           => $this->nextCode(Defect::class, $project->id, 'DEF'),
                    'title'          => Str::limit('Failed check: ' . $item->description, 195, ''),
                    'description'    => $data['notes'] ?? null,
                    'severity'       => $data['defect_severity'],
                    'status'         => 'open',
                    'requirement_id' => $checklist->wbsItem?->requirement_id,
                    'reported_by'    => $data['inspected_by'] ?? null,
                    'reported_on'    => $data['inspected_on'],
                ]);
            }

            InspectionResult::create([
                'checklist_item_id' => $item->id,
                'inspected_by'      => $data['inspected_by'] ?? null,
                'inspection_type'   => $data['inspection_type'],
                'result'            => $data['result'],
                'inspected_on'      => $data['inspected_on'],
                'notes'             => $data['notes'] ?? null,
                'defect_id'         => $defect?->id,
            ]);

            return $defect;
        });

        return back()->with('success', $defect
            ? "Result recorded and defect {$defect->code} raised."
            : 'Inspection result recorded.');
    }

    // ---- Risks: prioritization matrix ----------------------------------

    protected function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 15 => 'extreme',
            $score >= 10 => 'high',
            $score >= 5  => 'medium',
            default      => 'low',
        };
    }

    public function risksIndex(Project $project): View
    {
        $risks = $project->risks()->with('owner')->ranked()->get();

        // 5x5 prioritization matrix: rows = likelihood (5 to 1), columns = impact (1 to 5).
        $matrix = [];
        foreach (range(5, 1) as $likelihood) {
            foreach (range(1, 5) as $impact) {
                $matrix[$likelihood][$impact] = $risks->where('likelihood', $likelihood)->where('impact', $impact)->values();
            }
        }

        $levelCounts = $risks->whereIn('status', ['identified', 'mitigating'])->groupBy('level')->map->count();
        $members = $project->teamMembers()->orderBy('name')->get();

        return view('risks.index', compact('project', 'risks', 'matrix', 'levelCounts', 'members'));
    }

    protected function riskRules(Project $project): array
    {
        return [
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category'    => ['required', Rule::in(['technical', 'schedule', 'cost', 'quality', 'scope', 'resource', 'external'])],
            'likelihood'  => ['required', 'integer', 'between:1,5'],
            'impact'      => ['required', 'integer', 'between:1,5'],
            'owner_id'    => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $project->id)],
        ];
    }

    public function riskStore(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate($this->riskRules($project));

        $risk = $project->risks()->create($data + [
            'code'   => $this->nextCode(Risk::class, $project->id, 'RSK'),
            'level'  => $this->riskLevel((int) $data['likelihood'] * (int) $data['impact']),
            'status' => 'identified',
        ]);

        return back()->with('success', "Risk {$risk->code} added with a score of " . ((int) $data['likelihood'] * (int) $data['impact']) . '.');
    }

    public function riskUpdate(Request $request, Risk $risk): RedirectResponse
    {
        $data = $request->validate($this->riskRules($risk->project) + [
            'status' => ['required', Rule::in(['identified', 'mitigating', 'occurred', 'closed'])],
        ]);

        $data['level'] = $this->riskLevel((int) $data['likelihood'] * (int) $data['impact']);
        $risk->update($data);

        return back()->with('success', "Risk {$risk->code} updated.");
    }

    public function riskDestroy(Risk $risk): RedirectResponse
    {
        $risk->delete();

        return back()->with('success', 'Risk deleted along with its response plan.');
    }

    // ---- PDPC (Process Decision Program Chart) ---------------------------

    public function pdpcIndex(Project $project): View
    {
        $risks = $project->risks()->open()->ranked()->with(['owner', 'rootResponses.owner'])->get();

        // High and extreme risks that have no planned response yet.
        $unmitigated = $risks->filter(fn (Risk $r) => $r->score >= 10 && $r->rootResponses->isEmpty());
        $members = $project->teamMembers()->orderBy('name')->get();

        return view('pdpc.index', compact('project', 'risks', 'unmitigated', 'members'));
    }

    public function responseStore(Request $request, Risk $risk): RedirectResponse
    {
        $data = $request->validate([
            'node_type'   => ['required', Rule::in(['potential_problem', 'preventive_action', 'corrective_action', 'contingency'])],
            'description' => ['required', 'string', 'max:400'],
            'parent_id'   => ['nullable', Rule::exists('risk_responses', 'id')->where('risk_id', $risk->id)],
            'owner_id'    => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $risk->project_id)],
        ]);

        $parent = isset($data['parent_id']) ? RiskResponse::find($data['parent_id']) : null;
        $type = $data['node_type'];

        // PDPC structure: problem -> preventive / corrective action -> contingency.
        if ($type === 'potential_problem' && $parent) {
            return $this->fail('A potential problem is a top-level node; it cannot sit under another node.');
        }
        if (in_array($type, ['preventive_action', 'corrective_action'], true)
            && (!$parent || $parent->node_type !== 'potential_problem')) {
            return $this->fail('Preventive and corrective actions must be attached to a potential problem.');
        }
        if ($type === 'contingency'
            && (!$parent || !in_array($parent->node_type, ['preventive_action', 'corrective_action'], true))) {
            return $this->fail('A contingency must be attached to a preventive or corrective action.');
        }

        $risk->responses()->create([
            'parent_id'   => $parent?->id,
            'node_type'   => $type,
            'description' => $data['description'],
            'owner_id'    => $data['owner_id'] ?? null,
            'status'      => 'planned',
        ]);

        // A risk with a preventive or corrective action is being mitigated.
        if (in_array($type, ['preventive_action', 'corrective_action'], true) && $risk->status === 'identified') {
            $risk->update(['status' => 'mitigating']);
        }

        return back()->with('success', 'PDPC node added.');
    }

    public function responseUpdate(Request $request, RiskResponse $response): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:400'],
            'status'      => ['required', Rule::in(['planned', 'in_progress', 'done'])],
            'owner_id'    => ['nullable', Rule::exists('team_members', 'id')->where('project_id', $response->risk->project_id)],
        ]);

        $response->update($data);

        return back()->with('success', 'PDPC node updated.');
    }

    public function responseDestroy(RiskResponse $response): RedirectResponse
    {
        $response->delete();

        return back()->with('success', 'PDPC node deleted along with the nodes under it.');
    }

    // =====================================================================
    // MODULE 6: INTEGRATION, VALIDATION & REPORTING
    // =====================================================================

    public function dashboard(Project $project): View
    {
        return view('dashboard.index', $this->buildSummary($project));
    }

    public function report(Project $project): View
    {
        $summary = $this->buildSummary($project);

        $summary += [
            'changeRequests' => $project->changeRequests()->with('requirements')->orderBy('code')->get(),
            'defects'        => $project->defects()->with('assignee')->orderByDesc('reported_on')->get(),
            'requirements'   => $project->requirements()->orderBy('code')->get(),
            'activities'     => $project->activities()->with('assignee')->orderBy('planned_start')->get(),
            'snapshots'      => $project->evmSnapshots()->orderBy('status_date')->get(),
            'risks'          => $project->risks()->ranked()->get(),
        ];

        return view('reports.summary', $summary);
    }

    protected function buildSummary(Project $project): array
    {
        $asOf = $this->statusDate();
        $analysis = $this->refreshSchedule($project, $asOf);
        $evm = $this->computeEvm($project, $asOf);

        $current = (float) $project->current_budget;
        $totalDays = max(1, $this->days($project->start_date, $project->current_deadline) + 1);

        return [
            'project'               => $project,
            'asOf'                  => $asOf,
            'evm'                   => $evm,
            'analysis'              => $analysis,
            'thresholds'            => $this->thresholds($project),
            'warnings'              => $this->evaluateWarnings($project, $evm, $analysis),
            'consistency'           => $this->consistencyChecks($project, $analysis, $evm),
            'progress'              => [
                'complete'    => $evm['percent_complete'],
                'planned'     => $evm['percent_planned'],
                'time_elapsed' => max(0, min(100, round($this->days($project->start_date, $asOf) / $totalDays * 100, 1))),
            ],
            'budget'                => [
                'approved'  => (float) $project->approved_budget,
                'current'   => $current,
                'changes'   => $current - (float) $project->approved_budget,
                'planned'   => $evm['bac'],
                'actual'    => $evm['ac'],
                'remaining' => $current - $evm['ac'],
                'forecast'  => $evm['eac'],
                'used_pct'  => $current > 0 ? round($evm['ac'] / $current * 100, 1) : 0.0,
            ],
            'deadline'              => [
                'approved'  => $project->approved_deadline,
                'current'   => $project->current_deadline,
                'extension' => $this->days($project->approved_deadline, $project->current_deadline),
            ],
            'pendingChangeRequests' => $project->changeRequests()->pending()->with('requirements')->get(),
            'delayedActivities'     => $analysis['delays'],
            'criticalDefects'       => $project->defects()->unresolved()->critical()->with('assignee')->get(),
            'defectStats'           => $project->defects()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'requirementStats'      => $project->requirements()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'activityStats'         => $project->activities()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'milestones'            => $this->milestoneStates($project, $asOf),
            'topRisks'              => $project->risks()->open()->ranked()->with('owner')->limit(5)->get(),
        ];
    }

    /** Cross-module integrity checks: requirements, tasks, costs, change requests and quality records must agree. */
    protected function consistencyChecks(Project $project, array $analysis, array $evm): array
    {
        $issues = [];
        $add = function (string $level, string $message) use (&$issues) {
            $issues[] = ['level' => $level, 'message' => $message];
        };

        $outOfScope = $project->activities()->whereHas('requirement', fn ($q) => $q->where('in_scope', false))->count();
        if ($outOfScope > 0) {
            $add('error', "{$outOfScope} activity(ies) are linked to requirements that are not in the approved scope.");
        }

        $uncovered = $project->requirements()->inScope()->whereDoesntHave('activities')->count();
        if ($uncovered > 0) {
            $add('warning', "{$uncovered} approved requirement(s) have no planned activities.");
        }

        $noWbs = $project->requirements()->inScope()->whereDoesntHave('wbsItems')->count();
        if ($noWbs > 0) {
            $add('warning', "{$noWbs} approved requirement(s) are missing from the WBS.");
        }

        $emptyPackages = $project->wbsItems()->where('level_type', 'work_package')->whereDoesntHave('activities')->count();
        if ($emptyPackages > 0) {
            $add('warning', "{$emptyPackages} WBS work package(s) have no activities.");
        }

        $unassigned = $project->activities()->whereNull('assigned_to')->where('status', '!=', 'completed')->count();
        if ($unassigned > 0) {
            $add('warning', "{$unassigned} open activity(ies) have no assigned team member.");
        }

        $noCost = $project->activities()->where('estimated_cost', '<=', 0)->count();
        if ($noCost > 0) {
            $add('warning', "{$noCost} activity(ies) have no cost estimate, so they earn no value in EVM.");
        }

        if ($evm['bac'] > (float) $project->current_budget + 0.001) {
            $add('error', 'Planned activity costs exceed the approved budget.');
        }

        if ($analysis['planned_slip_days'] > 0) {
            $add('error', "The planned schedule finishes {$analysis['planned_slip_days']} day(s) after the deadline.");
        }

        $unlinkedCosts = $project->costEntries()->whereNull('activity_id')->count();
        if ($unlinkedCosts > 0) {
            $add('warning', "{$unlinkedCosts} cost entry(ies) are not linked to an activity.");
        }

        $brokenChange = $project->changeRequests()->whereIn('status', ['approved', 'implemented'])
            ->whereHas('requirements', fn ($q) => $q->where('in_scope', false))->count();
        if ($brokenChange > 0) {
            $add('error', "{$brokenChange} approved change request(s) have requirements that are still outside the scope.");
        }

        $defectOnDone = $project->defects()->unresolved()->whereHas('activity', fn ($q) => $q->where('status', 'completed'))->count();
        if ($defectOnDone > 0) {
            $add('warning', "{$defectOnDone} unresolved defect(s) are on activities already marked completed.");
        }

        $uncheckedDeliverables = $project->wbsItems()->where('level_type', 'deliverable')->whereDoesntHave('qualityChecklists')->count();
        if ($uncheckedDeliverables > 0) {
            $add('warning', "{$uncheckedDeliverables} deliverable(s) have no quality checklist.");
        }

        return $issues;
    }
}
