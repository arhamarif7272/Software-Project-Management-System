@extends('layouts.master')
@section('title', 'Work Breakdown Structure')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Work Breakdown Structure <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <form method="POST" action="{{ route('wbs.generate', $project) }}">@csrf
        <button class="btn btn-primary"><i class="bi bi-magic"></i> Generate from approved requirements</button>
    </form>
</div>

@if($uncovered->isNotEmpty())
    <div class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle"></i>
        {{ $uncovered->count() }} approved requirement(s) are not in the WBS yet:
        {{ $uncovered->pluck('code')->implode(', ') }}.
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-diagram-3"></i> Hierarchy: project, phase, deliverable, work package</div>
            <div class="card-body">
                @if($tree->isEmpty())
                    <div class="text-center text-muted py-4">The WBS is empty. Generate it from the approved requirements or add items manually.</div>
                @else
                    <ul class="tree root">
                        @foreach($tree as $node)
                            @include('wbs._node', ['node' => $node])
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Add WBS item</div>
            <div class="card-body">
                <form method="POST" action="{{ route('wbs.store', $project) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Name *</label><input name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label">Level *</label>
                        <select name="level_type" class="form-select form-select-sm">
                            @foreach(['project','phase','deliverable','work_package'] as $l)<option value="{{ $l }}">{{ ucwords(str_replace('_', ' ', $l)) }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Parent item</label>
                        <select name="parent_id" class="form-select form-select-sm"><option value="">None (top level)</option>
                            @foreach($items as $it)<option value="{{ $it->id }}">{{ $it->wbs_code }} {{ $it->name }} ({{ str_replace('_', ' ', $it->level_type) }})</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Linked approved requirement</label>
                        <select name="requirement_id" class="form-select form-select-sm"><option value="">None</option>
                            @foreach($scopeRequirements as $r)<option value="{{ $r->id }}">{{ $r->code }}: {{ \Illuminate\Support\Str::limit($r->title, 40) }}</option>@endforeach</select>
                        <div class="form-text">Only approved, in-scope requirements can be linked.</div></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control form-control-sm"></textarea></div>
                    <button class="btn btn-primary btn-sm">Add item</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection