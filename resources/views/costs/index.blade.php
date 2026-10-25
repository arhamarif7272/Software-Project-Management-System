@extends('layouts.master')
@section('title', 'Costs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Cost management <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <a href="{{ route('evm.index', $project) }}" class="btn btn-outline-primary"><i class="bi bi-graph-up-arrow"></i> Earned value analysis</a>
</div>

@php
    $overPlan = $totals['planned'] > $totals['current_budget'] + 0.001;
    $overSpend = $totals['actual'] > $totals['current_budget'] + 0.001;
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Approved budget</div><div class="stat-value money fs-4">PKR {{ number_format($totals['approved']) }}</div>
        <div class="small text-muted">Current: PKR {{ number_format($totals['current_budget']) }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $overPlan ? 'bad' : 'good' }}"><div class="card-body">
        <div class="stat-label">Total planned cost (activities)</div><div class="stat-value money fs-4">PKR {{ number_format($totals['planned']) }}</div>
        <div class="small {{ $overPlan ? 'text-danger fw-semibold' : 'text-muted' }}">
            {{ $overPlan ? 'Above the budget' : 'Unallocated: PKR ' . number_format($totals['unplanned']) }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $overSpend ? 'bad' : 'warn' }}"><div class="card-body">
        <div class="stat-label">Actual expenditure</div><div class="stat-value money fs-4">PKR {{ number_format($totals['actual']) }}</div>
        <div class="small text-muted">Unlinked to an activity: PKR {{ number_format($totals['unallocated']) }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $totals['remaining'] < 0 ? 'bad' : 'good' }}"><div class="card-body">
        <div class="stat-label">Budget remaining</div><div class="stat-value money fs-4">PKR {{ number_format($totals['remaining']) }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-calculator"></i> Activity cost estimates vs actuals</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Activity</th><th>Assigned</th><th>Status</th><th class="text-end">Estimated (BAC)</th><th class="text-end">Actual</th><th class="text-end">Variance</th><th class="text-center">% done</th></tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td><strong>{{ $r['activity']->code }}</strong> {{ \Illuminate\Support\Str::limit($r['activity']->name, 40) }}</td>
                            <td class="small">{{ $r['activity']->assignee?->name ?? '—' }}</td>
                            <td>@include('partials.badge', ['value' => $r['activity']->status])</td>
                            <td class="text-end money">{{ number_format($r['estimated']) }}</td>
                            <td class="text-end money">{{ number_format($r['actual']) }}</td>
                            <td class="text-end money {{ $r['variance'] < 0 ? 'text-danger fw-semibold' : 'text-success' }}">{{ number_format($r['variance']) }}</td>
                            <td class="text-center">{{ number_format($r['activity']->percent_complete, 0) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No activities yet.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot class="table-light fw-semibold"><tr>
                        <td colspan="3">Total</td>
                        <td class="text-end money">{{ number_format($totals['planned']) }}</td>
                        <td class="text-end money">{{ number_format($totals['actual']) }}</td>
                        <td class="text-end money">{{ number_format($totals['planned'] - $totals['actual']) }}</td><td></td></tr></tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-receipt"></i> Actual cost entries</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Activity</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th></th></tr></thead>
                    <tbody>
                    @forelse($entries as $e)
                        <tr>
                            <td>{{ $e->entry_date->format('d M Y') }}</td>
                            <td>{{ $e->activity?->code ?? '—' }}</td>
                            <td>{{ ucfirst($e->category) }}</td>
                            <td class="small">{{ $e->description }}</td>
                            <td class="text-end money">{{ number_format($e->amount) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('costs.destroy', $e) }}" data-confirm="Delete this cost entry?">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No expenditure recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-plus-circle"></i> Record actual cost</div>
            <div class="card-body">
                <form method="POST" action="{{ route('costs.store', $project) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Activity</label>
                        <select name="activity_id" class="form-select form-select-sm"><option value="">Not linked to an activity</option>
                            @foreach($activities as $a)<option value="{{ $a->id }}" @selected(old('activity_id') == $a->id)>{{ $a->code }} {{ \Illuminate\Support\Str::limit($a->name, 32) }}</option>@endforeach</select></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">Date *</label>
                            <input type="date" name="entry_date" value="{{ old('entry_date', $asOf->format('Y-m-d')) }}" class="form-control form-control-sm" required></div>
                        <div class="col-6"><label class="form-label">Category *</label>
                            <select name="category" class="form-select form-select-sm">
                                @foreach(['labor','infrastructure','license','testing','other'] as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach</select></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Amount (PKR) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label">Description</label>
                        <input name="description" value="{{ old('description') }}" class="form-control form-control-sm" maxlength="255"></div>
                    <button class="btn btn-primary btn-sm">Record cost</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart"></i> Spend by category</div>
            <ul class="list-group list-group-flush">
                @forelse($byCategory as $cat => $total)
                    <li class="list-group-item d-flex justify-content-between"><span>{{ ucfirst($cat) }}</span><span class="money">PKR {{ number_format($total) }}</span></li>
                @empty
                    <li class="list-group-item text-muted">Nothing recorded.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection