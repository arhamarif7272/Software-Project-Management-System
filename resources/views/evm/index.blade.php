@extends('layouts.master')
@section('title', 'Earned Value Management')

@section('content')
@php
    $cpiBad = $evm['cpi'] !== null && $evm['cpi'] < $thresholds['cpi'];
    $spiBad = $evm['spi'] !== null && $evm['spi'] < $thresholds['spi'];
    $cpiGood = $evm['cpi'] !== null && $evm['cpi'] >= 1;
    $spiGood = $evm['spi'] !== null && $evm['spi'] >= 1;
    $sevClass = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Earned value management <small class="text-muted fs-6">as at {{ $asOf->format('d M Y') }}</small></h3>
    <form method="POST" action="{{ route('evm.snapshot', $project) }}">@csrf
        <button class="btn btn-outline-primary"><i class="bi bi-camera"></i> Save snapshot and record alerts</button></form>
</div>

@forelse($warnings as $w)
    <div class="alert alert-{{ $sevClass[$w['severity']] ?? 'secondary' }} py-2 mb-2 d-flex align-items-start gap-2">
        <i class="bi {{ $w['severity'] === 'info' ? 'bi-info-circle' : 'bi-exclamation-triangle-fill' }} mt-1"></i>
        <div><strong>{{ ucwords(str_replace('_', ' ', $w['type'])) }}:</strong> {{ $w['message'] }}</div>
    </div>
@empty
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> No warnings. Cost and schedule performance are within the thresholds.</div>
@endforelse

<div class="row g-3 mb-3 mt-1">
    @foreach([['PV', 'Planned value', $evm['pv']], ['EV', 'Earned value', $evm['ev']], ['AC', 'Actual cost', $evm['ac']], ['BAC', 'Budget at completion', $evm['bac']]] as [$abbr, $label, $val])
        <div class="col-6 col-lg-3"><div class="card stat-card"><div class="card-body">
            <div class="stat-label">{{ $abbr }}: {{ $label }}</div><div class="stat-value money fs-4">PKR {{ number_format($val) }}</div></div></div></div>
    @endforeach
    <div class="col-6 col-lg-3"><div class="card stat-card {{ $cpiBad ? 'bad' : ($cpiGood ? 'good' : 'warn') }}"><div class="card-body">
        <div class="stat-label">CPI (EV / AC)</div><div class="stat-value">{{ $evm['cpi'] !== null ? number_format($evm['cpi'], 2) : 'n/a' }}</div>
        <div class="small text-muted">{{ $evm['cost_status'] }} · threshold {{ number_format($thresholds['cpi'], 2) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card {{ $spiBad ? 'bad' : ($spiGood ? 'good' : 'warn') }}"><div class="card-body">
        <div class="stat-label">SPI (EV / PV)</div><div class="stat-value">{{ $evm['spi'] !== null ? number_format($evm['spi'], 2) : 'n/a' }}</div>
        <div class="small text-muted">{{ $evm['schedule_status'] }} · threshold {{ number_format($thresholds['spi'], 2) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card {{ $evm['cv'] < 0 ? 'bad' : 'good' }}"><div class="card-body">
        <div class="stat-label">CV (EV − AC)</div><div class="stat-value money fs-4">{{ number_format($evm['cv']) }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card {{ $evm['sv'] < 0 ? 'bad' : 'good' }}"><div class="card-body">
        <div class="stat-label">SV (EV − PV)</div><div class="stat-value money fs-4">{{ number_format($evm['sv']) }}</div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-header"><i class="bi bi-graph-up"></i> S-curve: planned value vs actual cost</div>
            <div class="card-body chart-box"><canvas id="sCurve" height="130"></canvas></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header"><i class="bi bi-bar-chart"></i> PV, EV and AC</div>
            <div class="card-body chart-box"><canvas id="evmBars" height="200"></canvas></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-bullseye"></i> Forecast</div>
            <table class="table table-sm mb-0">
                <tr><td>EAC: estimate at completion (BAC / CPI)</td><td class="text-end money">{{ $evm['eac'] !== null ? 'PKR ' . number_format($evm['eac']) : 'n/a' }}</td></tr>
                <tr><td>ETC: estimate to complete (EAC − AC)</td><td class="text-end money">{{ $evm['etc'] !== null ? 'PKR ' . number_format($evm['etc']) : 'n/a' }}</td></tr>
                <tr><td>VAC: variance at completion (BAC − EAC)</td><td class="text-end money {{ ($evm['vac'] ?? 0) < 0 ? 'text-danger fw-semibold' : '' }}">{{ $evm['vac'] !== null ? 'PKR ' . number_format($evm['vac']) : 'n/a' }}</td></tr>
                <tr><td>Approved (current) budget</td><td class="text-end money">PKR {{ number_format($project->current_budget) }}</td></tr>
                <tr><td>% complete (EV / BAC)</td><td class="text-end">{{ number_format($evm['percent_complete'], 1) }}%</td></tr>
                <tr><td>% planned (PV / BAC)</td><td class="text-end">{{ number_format($evm['percent_planned'], 1) }}%</td></tr>
                <tr><td>% spent (AC / BAC)</td><td class="text-end">{{ number_format($evm['percent_spent'], 1) }}%</td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> Snapshot history</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Date</th><th class="text-end">PV</th><th class="text-end">EV</th><th class="text-end">AC</th><th class="text-end">CPI</th><th class="text-end">SPI</th><th class="text-end">EAC</th></tr></thead>
                    <tbody>
                    @forelse($snapshots as $s)
                        <tr>
                            <td>{{ $s->status_date->format('d M Y') }}</td>
                            <td class="text-end money">{{ number_format($s->pv) }}</td>
                            <td class="text-end money">{{ number_format($s->ev) }}</td>
                            <td class="text-end money">{{ number_format($s->ac) }}</td>
                            <td class="text-end {{ $s->cpi !== null && $s->cpi < $thresholds['cpi'] ? 'text-danger fw-semibold' : '' }}">{{ $s->cpi !== null ? number_format($s->cpi, 2) : '—' }}</td>
                            <td class="text-end {{ $s->spi !== null && $s->spi < $thresholds['spi'] ? 'text-danger fw-semibold' : '' }}">{{ $s->spi !== null ? number_format($s->spi, 2) : '—' }}</td>
                            <td class="text-end money">{{ $s->eac !== null ? number_format($s->eac) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No snapshots saved yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($unresolvedAlerts->isNotEmpty())
    <div class="card mt-3">
        <div class="card-header"><i class="bi bi-bell"></i> Recorded alerts</div>
        <ul class="list-group list-group-flush">
            @foreach($unresolvedAlerts as $al)
                <li class="list-group-item small d-flex justify-content-between">
                    <span>@include('partials.badge', ['value' => $al->severity]) {{ $al->message }}</span>
                    <span class="text-muted">{{ $al->created_at?->format('d M H:i') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const curve = @json($curve);
    const evm = @json($evm);

    new Chart(document.getElementById('sCurve'), {
        type: 'line',
        data: {
            labels: curve.labels,
            datasets: [
                { label: 'Planned value (PV)', data: curve.pv, borderColor: '#124E66', backgroundColor: 'rgba(18,78,102,.12)', fill: true, tension: .25, pointRadius: 2 },
                { label: 'Actual cost (AC)', data: curve.ac, borderColor: '#BC4749', tension: .25, pointRadius: 3, spanGaps: false },
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            scales: { y: { ticks: { callback: v => (v / 1000000).toFixed(1) + 'M' } } } }
    });

    new Chart(document.getElementById('evmBars'), {
        type: 'bar',
        data: { labels: ['PV', 'EV', 'AC'],
            datasets: [{ data: [evm.pv, evm.ev, evm.ac], backgroundColor: ['#124E66', '#1E6B58', '#BC4749'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { y: { ticks: { callback: v => (v / 1000000).toFixed(1) + 'M' } } } }
    });
</script>
@endpush