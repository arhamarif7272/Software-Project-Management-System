@php
    $labels = [
        'potential_problem' => 'Potential problem',
        'preventive_action' => 'Preventive action',
        'corrective_action' => 'Corrective action',
        'contingency'       => 'Contingency',
    ];
    $childTypes = match ($node->node_type) {
        'potential_problem' => ['preventive_action' => 'Preventive action', 'corrective_action' => 'Corrective action'],
        'preventive_action', 'corrective_action' => ['contingency' => 'Contingency'],
        default => [],
    };
    $cssType = $node->node_type === 'potential_problem' ? 'problem' : $node->node_type;
@endphp
<li>
    <div class="tree-node pdpc-{{ $cssType }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
                <div class="small text-muted text-uppercase" style="font-size:.66rem;letter-spacing:.5px">{{ $labels[$node->node_type] }}</div>
                <div>{{ $node->description }}</div>
                <div class="small text-muted">{{ $node->owner?->name ?? 'No owner' }}</div>
            </div>
            <div class="d-flex align-items-center gap-1 text-nowrap">
                @include('partials.badge', ['value' => $node->status])
                <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="collapse" data-bs-target="#node{{ $node->id }}"><i class="bi bi-pencil"></i></button>
                <form method="POST" action="{{ route('responses.destroy', $node) }}" data-confirm="Delete this node and everything under it?" class="m-0">
                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>
            </div>
        </div>

        <div class="collapse mt-2" id="node{{ $node->id }}">
            <form method="POST" action="{{ route('responses.update', $node) }}" class="row g-2 mb-2">
                @csrf @method('PUT')
                <div class="col-md-5"><input name="description" class="form-control form-control-sm" value="{{ $node->description }}" required maxlength="400"></div>
                <div class="col-md-3"><select name="status" class="form-select form-select-sm">
                    @foreach(['planned', 'in_progress', 'done'] as $s)<option value="{{ $s }}" @selected($node->status === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="owner_id" class="form-select form-select-sm"><option value="">Nobody</option>
                    @foreach($members as $m)<option value="{{ $m->id }}" @selected($node->owner_id === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Save</button></div>
            </form>

            @if($childTypes)
                <form method="POST" action="{{ route('responses.store', $risk) }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $node->id }}">
                    <div class="col-md-3"><select name="node_type" class="form-select form-select-sm">
                        @foreach($childTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-5"><input name="description" class="form-control form-control-sm" placeholder="Describe the action…" required maxlength="400"></div>
                    <div class="col-md-3"><select name="owner_id" class="form-select form-select-sm"><option value="">Owner: nobody</option>
                        @foreach($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
                </form>
            @endif
        </div>
    </div>

    @if($node->childrenRecursive->isNotEmpty())
        <ul class="tree">
            @foreach($node->childrenRecursive as $child)
                @include('pdpc._node', ['node' => $child, 'risk' => $risk, 'members' => $members])
            @endforeach
        </ul>
    @endif
</li>
