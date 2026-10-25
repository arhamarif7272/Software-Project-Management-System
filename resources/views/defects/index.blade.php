@extends('layouts.master')
@section('title', 'Defects')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Defect tracking <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <div class="d-flex gap-2 small">
        <span class="badge text-bg-secondary fs-6">Total: {{ $stats['total'] }}</span>
        <span class="badge text-bg-warning text-dark fs-6">Unresolved: {{ $stats['unresolved'] }}</span>
        <span class="badge text-bg-danger fs-6">Critical: {{ $stats['critical'] }}</span>
        <span class="badge text-bg-success fs-6">Closed: {{ $stats['closed'] }}</span>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm"><option value="">All</option>
                    @foreach(['open','in_progress','resolved','verified','closed','reopened'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Severity</label>
                <select name="severity" class="form-select form-select-sm"><option value="">All</option>
                    @foreach(['critical','high','medium','low'] as $s)
                        <option value="{{ $s }}" @selected(request('severity') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Assigned to</label>
                <select name="assigned_to" class="form-select form-select-sm"><option value="">Anyone</option>
                    @foreach($members as $m)<option value="{{ $m->id }}" @selected((string) request('assigned_to') === (string) $m->id)>{{ $m->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('defects.index', $project) }}" class="btn btn-sm btn-outline-secondary">Clear</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr>
                <th>Code</th><th>Defect</th><th>Severity</th><th>Status</th><th>Assigned</th>
                <th>Reported</th><th>Resolved</th><th></th></tr></thead>
            <tbody>
            @forelse($defects as $d)
                <tr>
                    <td class="fw-semibold">{{ $d->code }}</td>
                    <td>{{ $d->title }}
                        @if($d->requirement)<div class="small text-muted">Req {{ $d->requirement->code }}</div>@endif
                        @if($d->activity)<div class="small text-muted">Act {{ $d->activity->code }}</div>@endif</td>
                    <td>@include('partials.badge', ['value' => $d->severity])</td>
                    <td>@include('partials.badge', ['value' => $d->status])</td>
                    <td class="small">{{ $d->assignee?->name ?? '—' }}</td>
                    <td class="small">{{ $d->reported_on->format('d M Y') }}</td>
                    <td class="small">{{ $d->resolved_on?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#def{{ $d->id }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('defects.destroy', $d) }}" data-confirm="Delete {{ $d->code }}?" class="d-inline">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
                <tr class="collapse muted-row" id="def{{ $d->id }}">
                    <td colspan="8">
                        <form method="POST" action="{{ route('defects.update', $d) }}" class="row g-2">
                            @csrf @method('PUT')
                            <div class="col-md-5"><label class="form-label">Title</label><input name="title" class="form-control form-control-sm" value="{{ $d->title }}" required></div>
                            <div class="col-md-3"><label class="form-label">Severity</label><select name="severity" class="form-select form-select-sm">
                                @foreach(['low','medium','high','critical'] as $s)<option value="{{ $s }}" @selected($d->severity === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">
                                @foreach($transitions[$d->status] ?? [$d->status] as $s)<option value="{{ $d->status }}" hidden>{{ $d->status }}</option>
                                <option value="{{ $s }}" @selected($s === $d->status)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Assigned to</label><select name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>
                                @foreach($members as $m)<option value="{{ $m->id }}" @selected($d->assigned_to === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Requirement</label><select name="requirement_id" class="form-select form-select-sm"><option value="">None</option>
                                @foreach($requirements as $r)<option value="{{ $r->id }}" @selected($d->requirement_id === $r->id)>{{ $r->code }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Activity</label><select name="activity_id" class="form-select form-select-sm"><option value="">None</option>
                                @foreach($activities as $a)<option value="{{ $a->id }}" @selected($d->activity_id === $a->id)>{{ $a->code }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Reported on</label><input type="date" name="reported_on" class="form-control form-control-sm" value="{{ $d->reported_on->format('Y-m-d') }}" required></div>
                            <div class="col-md-3"><label class="form-label">Resolved on</label><input type="date" name="resolved_on" class="form-control form-control-sm" value="{{ $d->resolved_on?->format('Y-m-d') }}"></div>
                            <div class="col-md-3"><label class="form-label">Reported by</label><select name="reported_by" class="form-select form-select-sm"><option value="">—</option>
                                @foreach($members as $m)<option value="{{ $m->id }}" @selected($d->reported_by === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                            <div class="col-12"><label class="form-label">Resolution notes</label><textarea name="resolution_notes" rows="2" class="form-control form-control-sm">{{ $d->resolution_notes }}</textarea></div>
                            <div class="col-12"><button class="btn btn-sm btn-primary">Save</button></div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No defects match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-plus-circle"></i> Log new defect</div>
    <div class="card-body">
        <form method="POST" action="{{ route('defects.store', $project) }}" class="row g-2">
            @csrf
            <div class="col-md-5"><label class="form-label">Title *</label><input name="title" class="form-control form-control-sm" value="{{ old('title') }}" required></div>
            <div class="col-md-2"><label class="form-label">Severity *</label><select name="severity" class="form-select form-select-sm">
                @foreach(['low','medium','high','critical'] as $s)<option value="{{ $s }}" @selected(old('severity') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Assigned to</label><select name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>
                @foreach($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Reported on *</label><input type="date" name="reported_on" class="form-control form-control-sm" value="{{ old('reported_on', now()->format('Y-m-d')) }}" required></div>
            <div class="col-md-6"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control form-control-sm">{{ old('description') }}</textarea></div>
            <div class="col-md-3"><label class="form-label">Requirement</label><select name="requirement_id" class="form-select form-select-sm"><option value="">None</option>
                @foreach($requirements as $r)<option value="{{ $r->id }}">{{ $r->code }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Activity</label><select name="activity_id" class="form-select form-select-sm"><option value="">None</option>
                @foreach($activities as $a)<option value="{{ $a->id }}">{{ $a->code }}</option>@endforeach</select></div>
            <div class="col-12"><button class="btn btn-primary btn-sm">Log defect</button></div>
        </form>
    </div>
</div>
@endsection