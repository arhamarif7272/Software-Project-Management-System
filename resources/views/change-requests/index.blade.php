@extends('layouts.master')
@section('title', 'Change Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Change requests <small class="text-muted fs-6">{{ $project->name }}</small></h3>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Approved baseline budget</div>
        <div class="stat-value money">PKR {{ number_format($project->approved_budget) }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $totals['cost'] > 0 ? 'warn' : 'good' }}"><div class="card-body">
        <div class="stat-label">Current budget</div>
        <div class="stat-value money">PKR {{ number_format($project->current_budget) }}</div>
        <div class="small text-muted">+ PKR {{ number_format($totals['cost']) }} from changes</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Approved deadline</div>
        <div class="stat-value">{{ $project->approved_deadline->format('d M Y') }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $totals['days'] > 0 ? 'warn' : 'good' }}"><div class="card-body">
        <div class="stat-label">Current deadline</div>
        <div class="stat-value">{{ $project->current_deadline->format('d M Y') }}</div>
        <div class="small text-muted">+ {{ $totals['days'] }} days ({{ round($totals['days'] / 7, 1) }} weeks) from changes</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        @forelse($changeRequests as $cr)
            @php $pv = $previews[$cr->id]; @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><span class="badge text-bg-dark">{{ $cr->code }}</span> {{ $cr->title }}</span>
                    @include('partials.badge', ['value' => $cr->status])
                </div>
                <div class="card-body">
                    <p class="mb-1">{{ $cr->description }}</p>
                    <p class="small text-muted mb-2"><strong>Justification:</strong> {{ $cr->justification }}<br>
                        <strong>Requested by:</strong> {{ $cr->requested_by }} on {{ $cr->created_at?->format('d M Y') }}</p>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100">
                                <div class="stat-label">{{ $pv['applied'] ? 'Budget impact (applied)' : 'Budget impact (if approved)' }}</div>
                                <div class="money">PKR {{ number_format($pv['budget_before']) }}
                                    <i class="bi bi-arrow-right"></i> <strong>PKR {{ number_format($pv['budget_after']) }}</strong></div>
                                <div class="small text-muted">Cost impact + PKR {{ number_format($cr->cost_impact) }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100">
                                <div class="stat-label">{{ $pv['applied'] ? 'Schedule impact (applied)' : 'Schedule impact (if approved)' }}</div>
                                <div>{{ \Carbon\Carbon::parse($pv['deadline_before'])->format('d M Y') }}
                                    <i class="bi bi-arrow-right"></i> <strong>{{ \Carbon\Carbon::parse($pv['deadline_after'])->format('d M Y') }}</strong></div>
                                <div class="small text-muted">+ {{ $cr->schedule_impact_days }} days ({{ round($cr->schedule_impact_days / 7, 1) }} weeks)</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="stat-label mb-1">Scope impact: requirements</div>
                        @forelse($cr->requirements as $req)
                            <span class="badge text-bg-light border text-dark me-1">{{ $req->code }}: {{ \Illuminate\Support\Str::limit($req->title, 45) }}
                                @include('partials.badge', ['value' => $req->in_scope ? 'approved' : $req->status])</span>
                        @empty
                            <span class="small text-muted">No requirements attached.</span>
                        @endforelse
                    </div>

                    @if($cr->status === 'pending')
                        <hr>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('change-requests.approve', $cr) }}" class="d-flex gap-2">
                                    @csrf
                                    <input name="decision_notes" class="form-control form-control-sm" placeholder="Decision notes (optional)">
                                    <button class="btn btn-sm btn-success text-nowrap" data-confirm="Approve {{ $cr->code }}? Budget, deadline and scope will change.">
                                        <i class="bi bi-check-lg"></i> Approve</button>
                                </form>
                            </div>
                            <div class="col-md-5">
                                <form method="POST" action="{{ route('change-requests.reject', $cr) }}" class="d-flex gap-2">
                                    @csrf
                                    <input name="decision_notes" class="form-control form-control-sm" placeholder="Reason *" required minlength="5">
                                    <button class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-x-lg"></i> Reject</button>
                                </form>
                            </div>
                            <div class="col-md-1">
                                <form method="POST" action="{{ route('change-requests.destroy', $cr) }}" data-confirm="Delete {{ $cr->code }}?">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary w-100" title="Delete"><i class="bi bi-trash"></i></button></form>
                            </div>
                        </div>
                    @else
                        <div class="small text-muted border-top pt-2">
                            Decision by {{ $cr->decider?->name ?? 'n/a' }} on {{ $cr->decided_at?->format('d M Y H:i') }}
                            @if($cr->decision_notes): "{{ $cr->decision_notes }}"@endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-center text-muted py-5">No change requests yet.</div></div>
        @endforelse
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Raise change request</div>
            <div class="card-body">
                <p class="small text-muted">Nothing changes in the scope, budget or deadline until the request is approved.</p>
                <form method="POST" action="{{ route('change-requests.store', $project) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Title *</label><input name="title" value="{{ old('title') }}" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label">Description *</label><textarea name="description" rows="2" class="form-control form-control-sm" required>{{ old('description') }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Justification *</label><textarea name="justification" rows="2" class="form-control form-control-sm" required>{{ old('justification') }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Requested by *</label><input name="requested_by" value="{{ old('requested_by') }}" class="form-control form-control-sm" required></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Cost impact (PKR) *</label>
                            <input type="number" step="0.01" min="0" name="cost_impact" value="{{ old('cost_impact', 0) }}" class="form-control form-control-sm" required></div>
                        <div class="col-6"><label class="form-label">Schedule impact (days) *</label>
                            <input type="number" min="0" name="schedule_impact_days" value="{{ old('schedule_impact_days', 0) }}" class="form-control form-control-sm" required></div>
                    </div>
                    <div class="mb-2"><label class="form-label">New requirements (one per line)</label>
                        <textarea name="new_requirements" rows="3" class="form-control form-control-sm" placeholder="AI-based preliminary diagnosis module&#10;Mobile application for patients">{{ old('new_requirements') }}</textarea></div>
                    @if($candidates->isNotEmpty())
                        <div class="mb-3"><label class="form-label">Or attach existing proposed requirements</label>
                            <select name="requirement_ids[]" multiple size="4" class="form-select form-select-sm">
                                @foreach($candidates as $c)<option value="{{ $c->id }}">{{ $c->code }}: {{ \Illuminate\Support\Str::limit($c->title, 40) }}</option>@endforeach
                            </select></div>
                    @endif
                    <button class="btn btn-primary btn-sm">Submit change request</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection