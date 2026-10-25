@extends('layouts.master')
@section('title', 'Project report')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h3 class="mb-0">Project status report</h3>
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / save as PDF</button>
</div>

<div class="card mb-3"><div class="card-body">
    <h4 class="mb-1">{{ $project->name }}</h4>
    <div class="text-muted mb-2">Report date: {{ $asOf->format('d M Y') }} · Status: {{ ucfirst($project->status) }}
        · Charter {{ $project->charter_approved ? 'approved ' . $project->charter_approved_at?->format('d M Y') : 'not approved' }}</div>
    <p class="mb-1"><strong>Objectives:</strong> {{ $project->objectives }}</p>
    <p class="mb-0"><strong>Scope boundaries:</strong> {{ $project->scope_boundaries }}</p>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card h-100"><div class="card-header">Budget</div>
        <table class="table table-sm mb-0">
            <tr><td>Approved budget (baseline)</td><td class="text-end money">PKR {{ number_format($budget['approved']) }}</td></tr>
            <tr><td>Approved change requests</td><td class="text-end money">PKR {{ number_format($budget['changes']) }}</td></tr>
            <tr><td><strong>Current budget</strong></td><td class="text-end money"><strong>PKR {{ number_format($budget['current']) }}</strong></td></tr>
            <tr><td>Planned cost of activities</td><td class="text-end money">PKR {{ number_format($budget['planned']) }}</td></tr>
            <tr><td>Actual cost to date</td><td class="text-end money">PKR {{ number_format($budget['actual']) }} ({{ $budget['used_pct'] }}%)</td></tr>
            <tr><td>Remaining</td><td class="text-end money">PKR {{ number_format($budget['remaining']) }}</td></tr>
            <tr><td>Forecast at completion (EAC)</td><td class="text-end money">{{ $budget['forecast'] !== null ? 'PKR ' . number_format($budget['forecast']) : 'n/a' }}</td></tr>
        </table></div></div>
    <div class="col-md-6"><div class="card h-100"><div class="card-header">Schedule and earned value</div>
        <table class="table table-sm mb-0">
            <tr><td>Start date</td><td class="text-end">{{ $project->start_date->format('d M Y') }}</td></tr>
            <tr><td>Approved deadline</td><td class="text-end">{{ $deadline['approved']->format('d M Y') }}</td></tr>
            <tr><td><strong>Current deadline</strong> (+{{ $deadline['extension'] }} days)</td><td class="text-end"><strong>{{ $deadline['current']->format('d M Y') }}</strong></td></tr>
            <tr><td>Projected finish</td><td class="text-end">{{ $analysis['projected_finish']->format('d M Y') }}</td></tr>
            <tr><td>PV / EV / AC</td><td class="text-end money">{{ number_format($evm['pv']) }} / {{ number_format($evm['ev']) }} / {{ number_format($evm['ac']) }}</td></tr>
            <tr><td>CPI</td><td class="text-end">{{ $evm['cpi'] !== null ? number_format($evm['cpi'], 2) : 'n/a' }} ({{ $evm['cost_status'] }})</td></tr>
            <tr><td>SPI</td><td class="text-end">{{ $evm['spi'] !== null ? number_format($evm['spi'], 2) : 'n/a' }} ({{ $evm['schedule_status'] }})</td></tr>
            <tr><td>Overall progress</td><td class="text-end">{{ number_format($progress['complete'], 1) }}% (planned {{ number_format($progress['planned'], 1) }}%)</td></tr>
        </table></div></div>
</div>

@if(count($warnings))
<div class="card mb-3"><div class="card-header">Warnings</div>
    <ul class="list-group list-group-flush">
        @foreach($warnings as $w)<li class="list-group-item small">@include('partials.badge', ['value' => $w['severity']]) {{ $w['message'] }}</li>@endforeach
    </ul></div>
@endif

<div class="card mb-3"><div class="card-header">Change requests</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Title</th><th>Status</th><th class="text-end">Cost impact</th><th class="text-end">Schedule impact</th><th>Budget after</th><th>Deadline after</th></tr></thead>
        <tbody>@forelse($changeRequests as $cr)
            <tr><td class="fw-semibold">{{ $cr->code }}</td><td>{{ $cr->title }}<div class="small text-muted">{{ $cr->requirements->pluck('code')->implode(', ') }}</div></td>
                <td>@include('partials.badge', ['value' => $cr->status])</td>
                <td class="text-end money">PKR {{ number_format($cr->cost_impact) }}</td><td class="text-end">{{ $cr->schedule_impact_days }} days</td>
                <td class="money">{{ $cr->budget_after ? 'PKR ' . number_format($cr->budget_after) : '—' }}</td>
                <td>{{ $cr->deadline_after?->format('d M Y') ?? '—' }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted">None.</td></tr>@endforelse</tbody>
    </table></div></div>

<div class="card mb-3"><div class="card-header">Requirements ({{ $requirements->count() }})</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Requirement</th><th>Priority</th><th>Status</th><th>In scope</th></tr></thead>
        <tbody>@foreach($requirements as $r)
            <tr><td class="fw-semibold">{{ $r->code }}</td><td>{{ $r->title }}</td><td>@include('partials.badge', ['value' => $r->priority])</td>
                <td>@include('partials.badge', ['value' => $r->status])</td><td>{{ $r->in_scope ? 'Yes' : 'No' }}</td></tr>
        @endforeach</tbody>
    </table></div></div>

<div class="card mb-3"><div class="card-header">Activities ({{ $activities->count() }})</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Activity</th><th>Assigned</th><th>Start</th><th>End</th><th>Status</th><th class="text-center">Done</th><th class="text-center">Delay</th><th class="text-end">Cost</th></tr></thead>
        <tbody>@foreach($activities as $a)
            <tr><td class="fw-semibold">{{ $a->code }}</td><td>{{ $a->name }}</td><td class="small">{{ $a->assignee?->name ?? '—' }}</td>
                <td class="small">{{ $a->planned_start?->format('d M') }}</td><td class="small">{{ $a->planned_end?->format('d M') }}</td>
                <td>@include('partials.badge', ['value' => $a->status])</td><td class="text-center">{{ number_format($a->percent_complete, 0) }}%</td>
                <td class="text-center">{{ $a->delay_days ? '+' . $a->delay_days . 'd' : '' }}</td><td class="text-end money">{{ number_format($a->estimated_cost) }}</td></tr>
        @endforeach</tbody>
    </table></div></div>

<div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Defects ({{ $defects->count() }})</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Defect</th><th>Severity</th><th>Status</th></tr></thead>
            <tbody>@forelse($defects as $d)
                <tr><td class="fw-semibold">{{ $d->code }}</td><td>{{ $d->title }}<div class="small text-muted">{{ $d->assignee?->name }}</div></td>
                    <td>@include('partials.badge', ['value' => $d->severity])</td><td>@include('partials.badge', ['value' => $d->status])</td></tr>
            @empty<tr><td colspan="4" class="text-center text-muted">None.</td></tr>@endforelse</tbody>
        </table></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Risks ranked by score</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Risk</th><th class="text-center">Score</th><th>Level</th><th>Status</th></tr></thead>
            <tbody>@forelse($risks as $r)
                <tr><td class="fw-semibold">{{ $r->code }}</td><td>{{ $r->title }}</td><td class="text-center">{{ $r->score }}</td>
                    <td>@include('partials.badge', ['value' => $r->level])</td><td>@include('partials.badge', ['value' => $r->status])</td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted">None.</td></tr>@endforelse</tbody>
        </table></div></div></div>
</div>

@if($snapshots->isNotEmpty())
<div class="card mb-3"><div class="card-header">EVM history</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Date</th><th class="text-end">PV</th><th class="text-end">EV</th><th class="text-end">AC</th><th class="text-end">CPI</th><th class="text-end">SPI</th><th class="text-end">EAC</th></tr></thead>
        <tbody>@foreach($snapshots as $s)
            <tr><td>{{ $s->status_date->format('d M Y') }}</td><td class="text-end money">{{ number_format($s->pv) }}</td><td class="text-end money">{{ number_format($s->ev) }}</td>
                <td class="text-end money">{{ number_format($s->ac) }}</td><td class="text-end">{{ $s->cpi !== null ? number_format($s->cpi, 2) : '—' }}</td>
                <td class="text-end">{{ $s->spi !== null ? number_format($s->spi, 2) : '—' }}</td><td class="text-end money">{{ $s->eac !== null ? number_format($s->eac) : '—' }}</td></tr>
        @endforeach</tbody>
    </table></div></div>
@endif

@if(count($consistency))
<div class="card"><div class="card-header">Consistency findings</div>
    <ul class="list-group list-group-flush">@foreach($consistency as $c)<li class="list-group-item small">{{ ucfirst($c['level']) }}: {{ $c['message'] }}</li>@endforeach</ul></div>
@endif
@endsection