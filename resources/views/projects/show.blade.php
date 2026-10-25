@extends('layouts.master')
@section('title', $project->name)

@section('content')
@php $locked = $project->charter_approved; $closed = $project->status === 'closed'; @endphp

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h3 class="mb-1">{{ $project->name }}</h3>
        @include('partials.badge', ['value' => $project->status])
        @if($locked)<span class="badge text-bg-success">Charter approved</span>@else<span class="badge text-bg-warning text-dark">Charter draft</span>@endif
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @unless($locked)
            <form method="POST" action="{{ route('charter.approve', $project) }}">@csrf
                <button class="btn btn-success"><i class="bi bi-patch-check"></i> Approve charter</button>
            </form>
        @endunless
        <a href="{{ route('dashboard', $project) }}" class="btn btn-primary"><i class="bi bi-speedometer2"></i> Open dashboard</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Approved budget</div><div class="stat-value money">PKR {{ number_format($project->approved_budget) }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $project->current_budget > $project->approved_budget ? 'warn' : 'good' }}"><div class="card-body">
        <div class="stat-label">Current budget (with approved changes)</div><div class="stat-value money">PKR {{ number_format($project->current_budget) }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Approved deadline</div><div class="stat-value">{{ $project->approved_deadline->format('d M Y') }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $project->current_deadline->gt($project->approved_deadline) ? 'warn' : 'good' }}"><div class="card-body">
        <div class="stat-label">Current deadline</div><div class="stat-value">{{ $project->current_deadline->format('d M Y') }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-file-earmark-text"></i> Project charter</div>
            <div class="card-body">
                @if($locked)
                    <div class="alert alert-info py-2 small">Budget, start date and deadline are locked after charter approval. Use a change request to alter them.</div>
                @endif
                <form method="POST" action="{{ route('projects.update', $project) }}">
                    @csrf @method('PUT')
                    <div class="mb-2"><label class="form-label">Project name *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $project->name) }}" required></div>
                    <div class="mb-2"><label class="form-label">Description</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $project->description) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Objectives *</label>
                        <textarea name="objectives" rows="3" class="form-control" required>{{ old('objectives', $project->objectives) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Scope boundaries *</label>
                        <textarea name="scope_boundaries" rows="3" class="form-control" required>{{ old('scope_boundaries', $project->scope_boundaries) }}</textarea></div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><label class="form-label">Approved budget (PKR)</label>
                            <input type="number" step="0.01" name="approved_budget" class="form-control" value="{{ old('approved_budget', $project->approved_budget) }}" {{ $locked ? 'readonly' : '' }} required></div>
                        <div class="col-md-4"><label class="form-label">Start date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $project->start_date->format('Y-m-d')) }}" {{ $locked ? 'readonly' : '' }} required></div>
                        <div class="col-md-4"><label class="form-label">Deadline</label>
                            <input type="date" name="approved_deadline" class="form-control" value="{{ old('approved_deadline', $project->approved_deadline->format('Y-m-d')) }}" {{ $locked ? 'readonly' : '' }} required></div>
                    </div>
                    <button class="btn btn-primary btn-sm" {{ $closed ? 'disabled' : '' }}>Save charter</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-people"></i> Stakeholders</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Role</th><th>Organization</th><th>Influence</th><th>Interest</th><th>Contact</th><th></th></tr></thead>
                    <tbody>
                    @forelse($project->stakeholders as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s->name }}</td><td>{{ $s->role }}</td><td>{{ $s->organization }}</td>
                            <td>@include('partials.badge', ['value' => $s->influence])</td>
                            <td>@include('partials.badge', ['value' => $s->interest])</td>
                            <td>{{ $s->contact }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('stakeholders.destroy', $s) }}" data-confirm="Remove this stakeholder?" class="d-inline">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No stakeholders yet. Add at least one to approve the charter.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <form method="POST" action="{{ route('stakeholders.store', $project) }}" class="row g-2">
                    @csrf
                    <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
                    <div class="col-md-2"><input name="role" class="form-control form-control-sm" placeholder="Role"></div>
                    <div class="col-md-3"><input name="organization" class="form-control form-control-sm" placeholder="Organization"></div>
                    <div class="col-md-2"><select name="influence" class="form-select form-select-sm" title="Influence">
                        @foreach(['low','medium','high'] as $l)<option value="{{ $l }}" @selected($l === 'medium')>Influence: {{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select name="interest" class="form-select form-select-sm" title="Interest">
                        @foreach(['low','medium','high'] as $l)<option value="{{ $l }}" @selected($l === 'medium')>Interest: {{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-9"><input name="contact" class="form-control form-control-sm" placeholder="Contact (email / phone)"></div>
                    <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">Add stakeholder</button></div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-badge"></i> Team and responsibilities</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Role</th><th>Email</th><th class="text-end">Daily rate</th><th></th></tr></thead>
                    <tbody>
                    @forelse($project->teamMembers as $m)
                        <tr>
                            <td class="fw-semibold">{{ $m->name }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $m->role)) }}</td>
                            <td>{{ $m->email }}</td>
                            <td class="text-end money">PKR {{ number_format($m->daily_rate) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('team.destroy', $m) }}" data-confirm="Remove {{ $m->name }}?" class="d-inline">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No team members yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <form method="POST" action="{{ route('team.store', $project) }}" class="row g-2">
                    @csrf
                    <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
                    <div class="col-md-3"><select name="role" class="form-select form-select-sm" required>
                        @foreach(['project_manager','backend_developer','frontend_developer','database_developer','qa_engineer','other'] as $r)
                            <option value="{{ $r }}">{{ ucwords(str_replace('_', ' ', $r)) }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input type="email" name="email" class="form-control form-control-sm" placeholder="Email"></div>
                    <div class="col-md-2"><input type="number" min="0" step="0.01" name="daily_rate" class="form-control form-control-sm" placeholder="Daily rate *" required></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Add</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-signpost"></i> Project status</div>
            <div class="card-body">
                <form method="POST" action="{{ route('projects.status', $project) }}" class="d-flex gap-2">
                    @csrf
                    <select name="status" class="form-select form-select-sm" {{ $closed ? 'disabled' : '' }}>
                        @foreach(['initiation','planning','executing','monitoring','closing'] as $s)
                            <option value="{{ $s }}" @selected($project->status === $s)>{{ ucfirst($s) }}</option>@endforeach
                    </select>
                    <button class="btn btn-sm btn-primary" {{ $closed ? 'disabled' : '' }}>Update</button>
                </form>
                <div class="form-text">Execution needs an approved charter and at least one approved requirement.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-journal-text"></i> Project management plan
                <span class="badge text-bg-light float-end">v{{ $project->plan?->version ?? 0 }}</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('plan.update', $project) }}">
                    @csrf @method('PUT')
                    <div class="mb-2"><label class="form-label">Scope</label>
                        <textarea name="scope_statement" rows="2" class="form-control">{{ old('scope_statement', $project->plan?->scope_statement) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Schedule</label>
                        <textarea name="schedule_approach" rows="2" class="form-control">{{ old('schedule_approach', $project->plan?->schedule_approach) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Cost</label>
                        <textarea name="cost_approach" rows="2" class="form-control">{{ old('cost_approach', $project->plan?->cost_approach) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Quality</label>
                        <textarea name="quality_approach" rows="2" class="form-control">{{ old('quality_approach', $project->plan?->quality_approach) }}</textarea></div>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label" title="Warn when CPI falls below this">CPI threshold</label>
                            <input type="number" step="0.01" min="0.1" max="2" name="cpi_threshold" class="form-control form-control-sm" value="{{ old('cpi_threshold', $thresholds['cpi']) }}" required></div>
                        <div class="col-4"><label class="form-label" title="Warn when SPI falls below this">SPI threshold</label>
                            <input type="number" step="0.01" min="0.1" max="2" name="spi_threshold" class="form-control form-control-sm" value="{{ old('spi_threshold', $thresholds['spi']) }}" required></div>
                        <div class="col-4"><label class="form-label" title="Warn when spending reaches this % of the budget">Budget alert %</label>
                            <input type="number" step="1" min="1" max="100" name="budget_alert_pct" class="form-control form-control-sm" value="{{ old('budget_alert_pct', $thresholds['budget_pct']) }}" required></div>
                    </div>
                    <button class="btn btn-primary btn-sm">Save plan</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-flag"></i> Milestones</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Milestone</th><th>Due</th><th>State</th><th></th></tr></thead>
                    <tbody>
                    @forelse($milestones as $ms)
                        <tr>
                            <td><div class="fw-semibold">{{ $ms->title }}</div><div class="small text-muted">{{ $ms->responsible?->name ?? 'Unassigned' }}</div></td>
                            <td>{{ $ms->due_date->format('d M Y') }}</td>
                            <td>@include('partials.badge', ['value' => $ms->state])</td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#ms{{ $ms->id }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('milestones.destroy', $ms) }}" data-confirm="Delete this milestone?" class="d-inline">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                        <tr class="collapse muted-row" id="ms{{ $ms->id }}">
                            <td colspan="4">
                                <form method="POST" action="{{ route('milestones.update', $ms) }}" class="row g-2">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="description" value="{{ $ms->description }}">
                                    <div class="col-12"><input name="title" class="form-control form-control-sm" value="{{ $ms->title }}" required></div>
                                    <div class="col-6"><input type="date" name="due_date" class="form-control form-control-sm" value="{{ $ms->due_date->format('Y-m-d') }}" required></div>
                                    <div class="col-6"><select name="status" class="form-select form-select-sm">
                                        @foreach(['pending','achieved','missed'] as $st)<option value="{{ $st }}" @selected($ms->status === $st)>{{ ucfirst($st) }}</option>@endforeach</select></div>
                                    <div class="col-8"><select name="responsible_id" class="form-select form-select-sm">
                                        <option value="">Responsible: nobody</option>
                                        @foreach($project->teamMembers as $tm)<option value="{{ $tm->id }}" @selected($ms->responsible_id === $tm->id)>{{ $tm->name }}</option>@endforeach</select></div>
                                    <div class="col-4"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No milestones yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <form method="POST" action="{{ route('milestones.store', $project) }}" class="row g-2">
                    @csrf
                    <div class="col-12"><input name="title" class="form-control form-control-sm" placeholder="Milestone title *" required></div>
                    <div class="col-6"><input type="date" name="due_date" class="form-control form-control-sm" min="{{ $project->start_date->format('Y-m-d') }}" max="{{ $project->current_deadline->format('Y-m-d') }}" required></div>
                    <div class="col-6"><select name="responsible_id" class="form-select form-select-sm">
                        <option value="">Responsible: nobody</option>
                        @foreach($project->teamMembers as $tm)<option value="{{ $tm->id }}">{{ $tm->name }}</option>@endforeach</select></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary">Add milestone</button></div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-door-closed"></i> Project closure</div>
            <div class="card-body">
                @if($closed)
                    <div class="alert alert-secondary mb-2 py-2">Closed on <strong>{{ $project->closure_date?->format('d M Y') }}</strong></div>
                    <div class="form-label">Summary</div><p class="small">{{ $project->closure_summary }}</p>
                    <div class="form-label">Lessons learned</div><p class="small mb-0">{{ $project->lessons_learned ?: 'None recorded.' }}</p>
                @else
                    <form method="POST" action="{{ route('projects.close', $project) }}">
                        @csrf
                        <div class="mb-2"><label class="form-label">Closure summary *</label>
                            <textarea name="closure_summary" rows="2" class="form-control" required minlength="10">{{ old('closure_summary') }}</textarea></div>
                        <div class="mb-2"><label class="form-label">Lessons learned</label>
                            <textarea name="lessons_learned" rows="2" class="form-control">{{ old('lessons_learned') }}</textarea></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label">Closure date</label><input type="date" name="closure_date" class="form-control form-control-sm"></div>
                            <div class="col-6 d-flex align-items-end"><div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirm_incomplete" value="1" id="confirmIncomplete">
                                <label class="form-check-label small" for="confirmIncomplete">Close even if activities are incomplete</label></div></div>
                        </div>
                        <button class="btn btn-outline-danger btn-sm" data-confirm="Close this project?">Close project</button>
                        <div class="form-text">Blocked while critical defects or pending change requests remain.</div>
                    </form>
                @endif
            </div>
        </div>

        @unless($locked)
            <form method="POST" action="{{ route('projects.destroy', $project) }}" data-confirm="Delete this project and all of its data?">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete project</button>
            </form>
        @endunless
    </div>
</div>
@endsection