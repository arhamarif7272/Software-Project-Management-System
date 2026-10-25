@extends('layouts.master')
@section('title', 'Risks')

@section('content')
@php
    $cellClass = fn ($score) => $score >= 15 ? 'cell-extreme' : ($score >= 10 ? 'cell-high' : ($score >= 5 ? 'cell-medium' : 'cell-low'));
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Risk prioritization matrix <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <div class="d-flex gap-2">
        @foreach(['extreme' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'success'] as $lvl => $color)
            <span class="badge text-bg-{{ $color }} {{ $color === 'warning' ? 'text-dark' : '' }} fs-6">{{ ucfirst($lvl) }}: {{ $levelCounts[$lvl] ?? 0 }}</span>
        @endforeach
        <a href="{{ route('pdpc.index', $project) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-signpost-split"></i> PDPC response plan</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-grid-3x3"></i> Likelihood × impact (score 1 to 25)</div>
            <div class="card-body">
                <table class="matrix w-100">
                    @foreach(range(5, 1) as $l)
                        <tr>
                            <td class="axis">{{ $l }}</td>
                            @foreach(range(1, 5) as $i)
                                @php $cell = $matrix[$l][$i]; @endphp
                                <td class="{{ $cellClass($l * $i) }}">
                                    <div class="text-muted" style="font-size:.65rem">{{ $l * $i }}</div>
                                    @foreach($cell as $r)
                                        <span class="risk-chip" title="{{ $r->title }}">{{ $r->code }}</span>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr><td class="axis"></td>@foreach(range(1, 5) as $i)<td class="axis">{{ $i }}</td>@endforeach</tr>
                </table>
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span>↑ Likelihood (rows)</span><span>Impact (columns) →</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Add risk</div>
            <div class="card-body">
                <form method="POST" action="{{ route('risks.store', $project) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Title *</label><input name="title" value="{{ old('title') }}" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control form-control-sm">{{ old('description') }}</textarea></div>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="form-label">Category</label><select name="category" class="form-select form-select-sm">
                            @foreach(['technical','schedule','cost','quality','scope','resource','external'] as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach</select></div>
                        <div class="col-4"><label class="form-label">Likelihood (1-5) *</label><select name="likelihood" class="form-select form-select-sm">
                            @foreach(range(1, 5) as $n)<option value="{{ $n }}" @selected($n === 3)>{{ $n }}</option>@endforeach</select></div>
                        <div class="col-4"><label class="form-label">Impact (1-5) *</label><select name="impact" class="form-select form-select-sm">
                            @foreach(range(1, 5) as $n)<option value="{{ $n }}" @selected($n === 3)>{{ $n }}</option>@endforeach</select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Owner</label><select name="owner_id" class="form-select form-select-sm"><option value="">Nobody</option>
                        @foreach($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                    <button class="btn btn-primary btn-sm">Add risk</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-sort-numeric-down-alt"></i> Ranked risk register</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Rank</th><th>Code</th><th>Risk</th><th>Category</th><th class="text-center">L</th><th class="text-center">I</th><th class="text-center">Score</th><th>Level</th><th>Status</th><th>Owner</th><th></th></tr></thead>
            <tbody>
            @forelse($risks as $r)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="fw-semibold">{{ $r->code }}</td>
                    <td>{{ $r->title }}</td>
                    <td class="small">{{ ucfirst($r->category) }}</td>
                    <td class="text-center">{{ $r->likelihood }}</td>
                    <td class="text-center">{{ $r->impact }}</td>
                    <td class="text-center"><span class="badge {{ $cellClass($r->score) }} text-dark border">{{ $r->score }}</span></td>
                    <td>@include('partials.badge', ['value' => $r->level])</td>
                    <td>@include('partials.badge', ['value' => $r->status])</td>
                    <td class="small">{{ $r->owner?->name ?? '—' }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#risk{{ $r->id }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('risks.destroy', $r) }}" data-confirm="Delete {{ $r->code }} and its response plan?" class="d-inline">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
                <tr class="collapse muted-row" id="risk{{ $r->id }}">
                    <td colspan="11">
                        <form method="POST" action="{{ route('risks.update', $r) }}" class="row g-2">
                            @csrf @method('PUT')
                            <div class="col-md-4"><label class="form-label">Title</label><input name="title" class="form-control form-control-sm" value="{{ $r->title }}" required></div>
                            <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select form-select-sm">
                                @foreach(['technical','schedule','cost','quality','scope','resource','external'] as $c)<option value="{{ $c }}" @selected($r->category === $c)>{{ ucfirst($c) }}</option>@endforeach</select></div>
                            <div class="col-md-1"><label class="form-label">L</label><select name="likelihood" class="form-select form-select-sm">
                                @foreach(range(1, 5) as $n)<option value="{{ $n }}" @selected($r->likelihood === $n)>{{ $n }}</option>@endforeach</select></div>
                            <div class="col-md-1"><label class="form-label">I</label><select name="impact" class="form-select form-select-sm">
                                @foreach(range(1, 5) as $n)<option value="{{ $n }}" @selected($r->impact === $n)>{{ $n }}</option>@endforeach</select></div>
                            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm">
                                @foreach(['identified','mitigating','occurred','closed'] as $s)<option value="{{ $s }}" @selected($r->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                            <div class="col-md-2"><label class="form-label">Owner</label><select name="owner_id" class="form-select form-select-sm"><option value="">Nobody</option>
                                @foreach($members as $m)<option value="{{ $m->id }}" @selected($r->owner_id === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                            <div class="col-md-10"><textarea name="description" rows="2" class="form-control form-control-sm" placeholder="Description">{{ $r->description }}</textarea></div>
                            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-muted py-4">No risks recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection