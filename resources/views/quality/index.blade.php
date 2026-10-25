@extends('layouts.master')
@section('title', 'Quality')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Quality checklists and inspections <small class="text-muted fs-6">{{ $project->name }}</small></h3>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        @forelse($checklists as $cl)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="bi bi-clipboard-check"></i> {{ $cl->name }}
                        @if($cl->wbsItem)<span class="badge text-bg-light border text-muted">{{ $cl->wbsItem->wbs_code }} {{ $cl->wbsItem->name }}</span>@endif</span>
                    <span class="d-flex align-items-center gap-2">
                        @if($cl->pass_rate !== null)
                            <span class="badge text-bg-{{ $cl->pass_rate >= 90 ? 'success' : ($cl->pass_rate >= 60 ? 'warning' : 'danger') }}">Pass rate {{ $cl->pass_rate }}%</span>
                        @else
                            <span class="badge text-bg-secondary">Not inspected</span>
                        @endif
                        <form method="POST" action="{{ route('checklists.destroy', $cl) }}" data-confirm="Delete this checklist and its results?" class="m-0">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th style="width:42%">Check</th><th>Latest result</th><th>Type</th><th>Inspector</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                        @forelse($cl->items as $item)
                            @php $res = $item->latestResult; @endphp
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>@if($res)@include('partials.badge', ['value' => $res->result])@else<span class="text-muted small">not checked</span>@endif
                                    @if($res?->defect_id)<span class="badge text-bg-danger" title="Defect raised"><i class="bi bi-bug"></i></span>@endif</td>
                                <td class="small">{{ $res ? ucwords(str_replace('_', ' ', $res->inspection_type)) : '' }}</td>
                                <td class="small">{{ $res?->inspector?->name }}</td>
                                <td class="small">{{ $res?->inspected_on?->format('d M Y') }}</td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#insp{{ $item->id }}">Record result</button></td>
                            </tr>
                            <tr class="collapse muted-row" id="insp{{ $item->id }}">
                                <td colspan="6">
                                    <form method="POST" action="{{ route('inspections.store', $item) }}" class="row g-2">
                                        @csrf
                                        <div class="col-md-3"><label class="form-label">Type</label><select name="inspection_type" class="form-select form-select-sm">
                                            @foreach(['inspection','unit_test','integration_test','system_test','uat'] as $t)<option value="{{ $t }}">{{ ucwords(str_replace('_', ' ', $t)) }}</option>@endforeach</select></div>
                                        <div class="col-md-2"><label class="form-label">Result</label><select name="result" class="form-select form-select-sm">
                                            <option value="pass">Pass</option><option value="fail">Fail</option><option value="not_applicable">Not applicable</option></select></div>
                                        <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="inspected_on" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" required></div>
                                        <div class="col-md-3"><label class="form-label">Inspector</label><select name="inspected_by" class="form-select form-select-sm"><option value="">Nobody</option>
                                            @foreach($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                                        <div class="col-md-12"><label class="form-label">Notes (required for a failed check)</label><textarea name="notes" rows="2" class="form-control form-control-sm"></textarea></div>
                                        <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="raise_defect" value="1" id="rd{{ $item->id }}">
                                            <label class="form-check-label small" for="rd{{ $item->id }}">Raise a defect for a failed check</label></div></div>
                                        <div class="col-md-3"><select name="defect_severity" class="form-select form-select-sm">
                                            <option value="">Defect severity…</option>@foreach(['low','medium','high','critical'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select></div>
                                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Save result</button></div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No items in this checklist.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                    <form method="POST" action="{{ route('checklist-items.store', $cl) }}" class="d-flex gap-2">
                        @csrf
                        <input name="description" class="form-control form-control-sm" placeholder="Add a check…" required maxlength="300">
                        <button class="btn btn-sm btn-outline-primary text-nowrap">Add check</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-center text-muted py-5">No quality checklists yet. Create one for a deliverable.</div></div>
        @endforelse
    </div>

    <div class="col-xl-3">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle"></i> New checklist</div>
            <div class="card-body">
                <form method="POST" action="{{ route('checklists.store', $project) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Name *</label><input name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label">Deliverable</label>
                        <select name="wbs_item_id" class="form-select form-select-sm"><option value="">None</option>
                            @foreach($deliverables as $w)<option value="{{ $w->id }}">{{ $w->wbs_code }} {{ \Illuminate\Support\Str::limit($w->name, 30) }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">Checks (one per line)</label>
                        <textarea name="items" rows="6" class="form-control form-control-sm" placeholder="Requirements traced to design&#10;Code reviewed&#10;Unit tests pass&#10;No open critical defects"></textarea></div>
                    <button class="btn btn-primary btn-sm w-100">Create checklist</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection