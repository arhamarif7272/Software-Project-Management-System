@extends('layouts.master')
@section('title', 'Requirements')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Requirements <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <div class="d-flex gap-2">
        @foreach(['proposed' => 'secondary', 'approved' => 'success', 'rejected' => 'danger', 'deferred' => 'warning'] as $st => $color)
            <span class="badge text-bg-{{ $color }} {{ $color === 'warning' ? 'text-dark' : '' }} fs-6">{{ ucfirst($st) }}: {{ $stats[$st] ?? 0 }}</span>
        @endforeach
    </div>
</div>

@if($scopeLocked)
    <div class="alert alert-info py-2"><i class="bi bi-lock"></i> The scope baseline is locked during execution. New requirements must be raised through a
        <a href="{{ route('change-requests.index', $project) }}">change request</a>; they join the scope only when that request is approved.</div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4"><label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-sm"><option value="">All</option>
                            @foreach(['proposed','approved','rejected','deferred'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Priority</label>
                        <select name="priority" class="form-select form-select-sm"><option value="">All</option>
                            @foreach(['critical','high','medium','low'] as $p)<option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
                    <div class="col-md-4"><button class="btn btn-sm btn-primary">Filter</button>
                        <a href="{{ route('requirements.index', $project) }}" class="btn btn-sm btn-outline-secondary">Clear</a></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Requirement</th><th>Type</th><th>Priority</th><th>Status</th><th>Scope</th><th></th></tr></thead>
                    <tbody>
                    @forelse($requirements as $r)
                        <tr>
                            <td class="fw-semibold">{{ $r->code }}</td>
                            <td>{{ $r->title }}
                                @if($r->originChangeRequest)<div class="small text-muted"><i class="bi bi-arrow-left-right"></i> via {{ $r->originChangeRequest->code }}</div>@endif
                            </td>
                            <td class="small">{{ ucwords(str_replace('_', ' ', $r->type)) }}</td>
                            <td>@include('partials.badge', ['value' => $r->priority])</td>
                            <td>@include('partials.badge', ['value' => $r->status])</td>
                            <td>@if($r->in_scope)<span class="badge text-bg-success">In scope</span>@else<span class="badge text-bg-light text-muted border">Not in scope</span>@endif</td>
                            <td class="text-end text-nowrap">
                                @if($r->status !== 'approved')
                                    <form method="POST" action="{{ route('requirements.approve', $r) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-success" title="Approve and add to scope"><i class="bi bi-check-lg"></i></button></form>
                                @endif
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#req{{ $r->id }}"><i class="bi bi-pencil"></i></button>
                                @unless($r->in_scope)
                                    <form method="POST" action="{{ route('requirements.destroy', $r) }}" data-confirm="Delete {{ $r->code }}?" class="d-inline">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                                @endunless
                            </td>
                        </tr>
                        <tr class="collapse muted-row" id="req{{ $r->id }}">
                            <td colspan="7">
                                @if($r->in_scope)<div class="small text-muted mb-2">Approved scope: only the priority can be changed here.</div>@endif
                                <form method="POST" action="{{ route('requirements.update', $r) }}" class="row g-2">
                                    @csrf @method('PUT')
                                    <div class="col-md-6"><input name="title" class="form-control form-control-sm" value="{{ $r->title }}" required></div>
                                    <div class="col-md-3"><select name="type" class="form-select form-select-sm">
                                        @foreach(['functional','non_functional','constraint'] as $t)<option value="{{ $t }}" @selected($r->type === $t)>{{ ucwords(str_replace('_', ' ', $t)) }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><select name="priority" class="form-select form-select-sm">
                                        @foreach(['low','medium','high','critical'] as $p)<option value="{{ $p }}" @selected($r->priority === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
                                    <div class="col-12"><textarea name="description" rows="2" class="form-control form-control-sm" placeholder="Description">{{ $r->description }}</textarea></div>
                                    <div class="col-12"><button class="btn btn-sm btn-primary">Save</button></div>
                                </form>
                                @unless($r->in_scope)
                                    <form method="POST" action="{{ route('requirements.status', $r) }}" class="d-flex gap-2 mt-2">
                                        @csrf
                                        <select name="status" class="form-select form-select-sm" style="max-width:180px">
                                            @foreach(['proposed','rejected','deferred'] as $s)<option value="{{ $s }}" @selected($r->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                                        <button class="btn btn-sm btn-outline-primary">Change status</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No requirements match.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Add requirement</div>
            <div class="card-body">
                <p class="small text-muted">New requirements start as <strong>proposed</strong> and stay outside the approved scope until approved.</p>
                <form method="POST" action="{{ route('requirements.store', $project) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Title *</label><input name="title" value="{{ old('title') }}" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control form-control-sm">{{ old('description') }}</textarea></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Type</label><select name="type" class="form-select form-select-sm">
                            @foreach(['functional','non_functional','constraint'] as $t)<option value="{{ $t }}">{{ ucwords(str_replace('_', ' ', $t)) }}</option>@endforeach</select></div>
                        <div class="col-6"><label class="form-label">Priority</label><select name="priority" class="form-select form-select-sm">
                            @foreach(['low','medium','high','critical'] as $p)<option value="{{ $p }}" @selected($p === 'medium')>{{ ucfirst($p) }}</option>@endforeach</select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Attach to a pending change request</label>
                        <select name="change_request_id" class="form-select form-select-sm">
                            <option value="">None (standalone requirement)</option>
                            @foreach($pendingChangeRequests as $cr)<option value="{{ $cr->id }}">{{ $cr->code }}: {{ $cr->title }}</option>@endforeach
                        </select></div>
                    <button class="btn btn-primary btn-sm">Add requirement</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection