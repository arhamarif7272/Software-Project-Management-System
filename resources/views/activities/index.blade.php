@extends('layouts.master')
@section('title', 'Activities')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Activities and schedule <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <a href="{{ route('schedule.gantt', $project) }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart-steps"></i> View Gantt chart</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Planned finish</div><div class="stat-value">{{ $analysis['planned_finish']->format('d M Y') }}</div>
        <div class="small text-muted">Deadline {{ $project->current_deadline->format('d M Y') }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $analysis['deadline_slip_days'] > 0 ? 'bad' : 'good' }}"><div class="card-body">
        <div class="stat-label">Projected finish at {{ $asOf->format('d M') }}</div><div class="stat-value">{{ $analysis['projected_finish']->format('d M Y') }}</div>
        <div class="small {{ $analysis['deadline_slip_days'] > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
            @if($analysis['deadline_slip_days'] > 0){{ $analysis['deadline_slip_days'] }} day(s) after the deadline @else Within the deadline @endif</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ count($analysis['delays']) ? 'warn' : 'good' }}"><div class="card-body">
        <div class="stat-label">Delayed activities</div><div class="stat-value">{{ count($analysis['delays']) }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Planned cost / budget</div>
        <div class="stat-value money fs-5">PKR {{ number_format($plannedCost) }}</div>
        <div class="small text-muted">of PKR {{ number_format($project->current_budget) }}</div></div></div></div>
</div>

@if(!empty($analysis['critical_path']))
    <div class="alert alert-light border py-2"><i class="bi bi-lightning-charge text-danger"></i>
        <strong>Critical path:</strong> {{ implode(' → ', $analysis['critical_path']) }}</div>
@endif

@if(count($analysis['delays']))
    <div class="card mb-3 border-warning border-start border-4">
        <div class="card-header"><i class="bi bi-exclamation-triangle text-warning"></i> Delay impact analysis</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Delayed activity</th><th>Delay</th><th>Dependent activities pushed out</th></tr></thead>
                <tbody>
                @foreach($analysis['delays'] as $d)
                    <tr>
                        <td><strong>{{ $d['activity']->code }}</strong> {{ $d['activity']->name }}</td>
                        <td><span class="badge text-bg-danger">+{{ $d['delay_days'] }} days</span></td>
                        <td class="small">
                            @forelse($d['impacted'] as $i)<span class="badge text-bg-light border text-dark me-1">{{ $i['code'] }} +{{ $i['delay_days'] }}d</span>
                            @empty <span class="text-muted">No dependent activity is affected.</span>@endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small {{ $analysis['deadline_slip_days'] > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
            Effect on the final deadline:
            @if($analysis['deadline_slip_days'] > 0) the project is projected to finish {{ $analysis['deadline_slip_days'] }} day(s) late ({{ $analysis['projected_finish']->format('d M Y') }}).
            @else the delays are absorbed by slack; the deadline is still met. @endif
        </div>
    </div>
@endif

@if(count($conflicts))
    <div class="alert alert-warning py-2"><strong><i class="bi bi-people"></i> Resource conflicts:</strong>
        <ul class="mb-0">@foreach($conflicts as $c)
            <li>{{ $c['member'] }} is booked on {{ $c['first'] }} and {{ $c['second'] }} at the same time ({{ $c['from'] }} to {{ $c['to'] }}, {{ $c['days'] }} day(s)).</li>
        @endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr>
                        <th>Code</th><th>Activity</th><th>Assigned</th><th>Start</th><th>End</th><th class="text-center">Days</th>
                        <th class="text-center">Slack</th><th>Progress</th><th>Status</th><th class="text-end">Cost</th><th></th></tr></thead>
                    <tbody>
                    @forelse($activities as $a)
                        <tr>
                            <td class="fw-semibold">{{ $a->code }} @if($a->is_critical)<i class="bi bi-lightning-charge-fill text-danger" title="Critical path"></i>@endif</td>
                            <td>{{ $a->name }}
                                @if($a->predecessors->isNotEmpty())<div class="small text-muted">After: {{ $a->predecessors->pluck('code')->implode(', ') }}</div>@endif
                            </td>
                            <td class="small">{{ $a->assignee?->name ?? '—' }}</td>
                            <td class="small">{{ $a->planned_start?->format('d M') }}</td>
                            <td class="small">{{ $a->planned_end?->format('d M') }}</td>
                            <td class="text-center">{{ $a->duration_days }}</td>
                            <td class="text-center">{{ $a->slack_days }}</td>
                            <td style="min-width:90px"><div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ $a->percent_complete }}%"></div></div>
                                <div class="small text-muted">{{ number_format($a->percent_complete, 0) }}%</div></td>
                            <td>@include('partials.badge', ['value' => $a->status]) @if($a->delay_days > 0)<span class="badge text-bg-danger">+{{ $a->delay_days }}d</span>@endif</td>
                            <td class="text-end money">{{ number_format($a->estimated_cost) }}</td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#act{{ $a->id }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('activities.destroy', $a) }}" data-confirm="Delete {{ $a->code }}?" class="d-inline">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                        <tr class="collapse muted-row" id="act{{ $a->id }}">
                            <td colspan="11">
                                <form method="POST" action="{{ route('activities.update', $a) }}" class="row g-2">
                                    @csrf @method('PUT')
                                    <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control form-control-sm" value="{{ $a->name }}" required></div>
                                    <div class="col-md-2"><label class="form-label">Duration (days)</label><input type="number" min="1" name="duration_days" class="form-control form-control-sm" value="{{ $a->duration_days }}" required></div>
                                    <div class="col-md-3"><label class="form-label">Assigned to</label><select name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>
                                        @foreach($members as $m)<option value="{{ $m->id }}" @selected($a->assigned_to === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><label class="form-label">Estimated cost (PKR)</label><input type="number" min="0" step="0.01" name="estimated_cost" class="form-control form-control-sm" value="{{ $a->estimated_cost }}"></div>
                                    <div class="col-md-4"><label class="form-label">WBS work package</label><select name="wbs_item_id" class="form-select form-select-sm"><option value="">None</option>
                                        @foreach($workPackages as $w)<option value="{{ $w->id }}" @selected($a->wbs_item_id === $w->id)>{{ $w->wbs_code }} {{ \Illuminate\Support\Str::limit($w->name, 35) }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label">Requirement</label><select name="requirement_id" class="form-select form-select-sm"><option value="">None</option>
                                        @foreach($scopeRequirements as $r)<option value="{{ $r->id }}" @selected($a->requirement_id === $r->id)>{{ $r->code }} {{ \Illuminate\Support\Str::limit($r->title, 30) }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label">Milestone</label><select name="milestone_id" class="form-select form-select-sm"><option value="">None</option>
                                        @foreach($milestones as $ms)<option value="{{ $ms->id }}" @selected($a->milestone_id === $ms->id)>{{ $ms->title }}</option>@endforeach</select></div>
                                    <div class="col-md-2"><label class="form-label">% complete</label><input type="number" min="0" max="100" step="1" name="percent_complete" class="form-control form-control-sm" value="{{ (float) $a->percent_complete }}"></div>
                                    <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">
                                        @php $current = $a->status === 'delayed' ? 'in_progress' : $a->status; @endphp
                                        @foreach(['not_started','in_progress','completed','on_hold'] as $s)<option value="{{ $s }}" @selected($current === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><label class="form-label">Actual start</label><input type="date" name="actual_start" class="form-control form-control-sm" value="{{ $a->actual_start?->format('Y-m-d') }}"></div>
                                    <div class="col-md-3"><label class="form-label">Actual end</label><input type="date" name="actual_end" class="form-control form-control-sm" value="{{ $a->actual_end?->format('Y-m-d') }}"></div>
                                    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                                </form>

                                <hr class="my-2">
                                <div class="small fw-semibold mb-1">Dependencies (this activity waits for)</div>
                                @forelse($a->dependencies as $dep)
                                    <form method="POST" action="{{ route('dependencies.destroy', $dep) }}" class="d-inline-block me-2">
                                        @csrf @method('DELETE')
                                        <span class="badge text-bg-light border text-dark">{{ $dep->predecessor->code }} <span class="text-muted">{{ $dep->dependency_type }}{{ $dep->lag_days ? ' ' . sprintf('%+d', $dep->lag_days) . 'd' : '' }}</span>
                                            <button class="btn btn-link btn-sm p-0 ms-1 text-danger" title="Remove">&times;</button></span>
                                    </form>
                                @empty
                                    <span class="small text-muted">None. This activity can start on day one.</span>
                                @endforelse
                                <form method="POST" action="{{ route('dependencies.store', $a) }}" class="row g-2 mt-1">
                                    @csrf
                                    <div class="col-md-4"><select name="predecessor_id" class="form-select form-select-sm" required><option value="">Predecessor…</option>
                                        @foreach($activities as $other)@if($other->id !== $a->id)<option value="{{ $other->id }}">{{ $other->code }} {{ \Illuminate\Support\Str::limit($other->name, 35) }}</option>@endif @endforeach</select></div>
                                    <div class="col-md-3"><select name="dependency_type" class="form-select form-select-sm">
                                        <option value="FS">Finish to start (FS)</option><option value="SS">Start to start (SS)</option>
                                        <option value="FF">Finish to finish (FF)</option><option value="SF">Start to finish (SF)</option></select></div>
                                    <div class="col-md-2"><input type="number" name="lag_days" class="form-control form-control-sm" placeholder="Lag (days)" value="0"></div>
                                    <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Add dependency</button></div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">No activities yet. Add the first one on the right.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-people"></i> Team workload (open activities)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Member</th><th>Role</th><th class="text-center">Open activities</th><th class="text-center">Planned days</th></tr></thead>
                    <tbody>@foreach($workload as $w)
                        <tr><td>{{ $w['member']->name }}</td><td class="small">{{ ucwords(str_replace('_', ' ', $w['member']->role)) }}</td>
                            <td class="text-center">{{ $w['count'] }}</td><td class="text-center">{{ $w['days'] }}</td></tr>
                    @endforeach</tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Add activity</div>
            <div class="card-body">
                <form method="POST" action="{{ route('activities.store', $project) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Name *</label><input name="name" value="{{ old('name') }}" class="form-control form-control-sm" required></div>
                    <div class="row g-2 mb-2">
                        <div class="col-5"><label class="form-label">Days *</label><input type="number" min="1" name="duration_days" value="{{ old('duration_days') }}" class="form-control form-control-sm" required></div>
                        <div class="col-7"><label class="form-label">Cost (PKR)</label><input type="number" min="0" step="0.01" name="estimated_cost" value="{{ old('estimated_cost') }}" class="form-control form-control-sm" placeholder="Auto: days x rate"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Assigned to</label><select name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>
                        @foreach($members as $m)<option value="{{ $m->id }}">{{ $m->name }} ({{ ucwords(str_replace('_', ' ', $m->role)) }})</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">WBS work package</label><select name="wbs_item_id" class="form-select form-select-sm"><option value="">None</option>
                        @foreach($workPackages as $w)<option value="{{ $w->id }}">{{ $w->wbs_code }} {{ \Illuminate\Support\Str::limit($w->name, 30) }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Approved requirement</label><select name="requirement_id" class="form-select form-select-sm"><option value="">None</option>
                        @foreach($scopeRequirements as $r)<option value="{{ $r->id }}">{{ $r->code }} {{ \Illuminate\Support\Str::limit($r->title, 28) }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Milestone</label><select name="milestone_id" class="form-select form-select-sm"><option value="">None</option>
                        @foreach($milestones as $ms)<option value="{{ $ms->id }}">{{ $ms->title }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">Predecessors (finish to start)</label>
                        <select name="predecessor_ids[]" multiple size="5" class="form-select form-select-sm">
                            @foreach($activities as $other)<option value="{{ $other->id }}">{{ $other->code }} {{ \Illuminate\Support\Str::limit($other->name, 28) }}</option>@endforeach</select>
                        <div class="form-text">Hold Ctrl to select several. Circular links are rejected.</div></div>
                    <button class="btn btn-primary btn-sm w-100">Add activity</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection