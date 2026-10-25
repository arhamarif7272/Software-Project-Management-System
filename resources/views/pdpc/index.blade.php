@extends('layouts.master')
@section('title', 'PDPC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Process Decision Program Chart <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <a href="{{ route('risks.index', $project) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-grid-3x3"></i> Risk matrix</a>
</div>

<div class="d-flex flex-wrap gap-3 small mb-3">
    <span><span class="badge" style="background:#dc3545">&nbsp;</span> Potential problem</span>
    <span><span class="badge" style="background:#198754">&nbsp;</span> Preventive action</span>
    <span><span class="badge" style="background:#0d6efd">&nbsp;</span> Corrective action</span>
    <span><span class="badge" style="background:#6f42c1">&nbsp;</span> Contingency</span>
</div>

@if($unmitigated->isNotEmpty())
    <div class="alert alert-danger py-2">
        <i class="bi bi-exclamation-octagon"></i>
        <strong>{{ $unmitigated->count() }} high/extreme risk(s) have no response plan:</strong>
        {{ $unmitigated->pluck('code')->implode(', ') }}
    </div>
@endif

@forelse($risks as $risk)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><span class="badge text-bg-dark">{{ $risk->code }}</span> {{ $risk->title }}
                <span class="text-muted small ms-2">score {{ $risk->score }} ({{ $risk->likelihood }} × {{ $risk->impact }})</span></span>
            <span>@include('partials.badge', ['value' => $risk->level]) @include('partials.badge', ['value' => $risk->status])</span>
        </div>
        <div class="card-body">
            @if($risk->rootResponses->isEmpty())
                <div class="text-muted small mb-2">No response plan yet. Start with a potential problem.</div>
            @else
                <ul class="tree root">
                    @foreach($risk->rootResponses as $node)
                        @include('pdpc._node', ['node' => $node, 'risk' => $risk, 'members' => $members])
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('responses.store', $risk) }}" class="row g-2 border-top pt-2 mt-2">
                @csrf
                <input type="hidden" name="node_type" value="potential_problem">
                <div class="col-md-7"><input name="description" class="form-control form-control-sm" placeholder="Add a potential problem for this risk…" required maxlength="400"></div>
                <div class="col-md-3"><select name="owner_id" class="form-select form-select-sm"><option value="">Owner: nobody</option>
                    @foreach($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-danger w-100">Add problem</button></div>
            </form>
        </div>
    </div>
@empty
    <div class="card"><div class="card-body text-center text-muted py-5">No open risks. Add risks first in the <a href="{{ route('risks.index', $project) }}">risk matrix</a>.</div></div>
@endforelse
@endsection