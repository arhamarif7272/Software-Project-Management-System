@extends('layouts.master')
@section('title', 'Dashboard')

@section('content')
@php
    $sev = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $cpiBad = $evm['cpi'] !== null && $evm['cpi'] < $thresholds['cpi'];
    $spiBad = $evm['spi'] !== null && $evm['spi'] < $thresholds['spi'];
    $budgetClass = $budget['actual'] > $budget['current'] ? 'bad' : ($budget['used_pct'] >= $thresholds['budget_pct'] ? 'warn' : 'good');
    $deadlineClass = $analysis['deadline_slip_days'] > 0 ? 'bad' : (count($delayedActivities) ? 'warn' : 'good');
    $progressClass = ($evm['percent_complete'] ?? 0) < ($evm['percent_planned'] ?? 0) - 5 ? 'warn' : 'good';
    $errors_ = collect($consistency)->where('level', 'error')->count();

    // ---- Build a small weekly sparkline for PV (planned) ----
    $curveActivities = $project->activities()->get();
    $curveStart      = $project->start_date->copy()->startOfDay();
    $curveTotalDays  = max(1, (int) round($curveStart->diffInDays($project->current_deadline->copy()->startOfDay(), false)) + 1);
    $sparkWeeks      = 10;
    $sparkStep       = max(1, (int) ceil($curveTotalDays / $sparkWeeks));
    $sparkLabels     = [];
    $sparkPV         = [];

    $plannedValueAt = function ($date) use ($curveActivities) {
        $pv = 0.0;
        foreach ($curveActivities as $a) {
            if (!$a->planned_start || !$a->planned_end) continue;
            $duration = max(1, (int) $a->duration_days);
            if ($date->lt($a->planned_start)) { $fraction = 0.0; }
            elseif ($date->gte($a->planned_end)) { $fraction = 1.0; }
            else { $fraction = ((int) round($a->planned_start->diffInDays($date, false)) + 1) / $duration; }
            $pv += (float) $a->estimated_cost * min(1.0, $fraction);
        }
        return round($pv, 2);
    };

    for ($i = 0; $i <= $sparkWeeks; $i++) {
        $d = $curveStart->copy()->addDays(min($i * $sparkStep, $curveTotalDays - 1));
        $sparkLabels[] = $d->format('d M');
        $sparkPV[] = $plannedValueAt($d);
    }

    // ---- Defect breakdown ----
    $dfOpen  = $defectStats['open'] ?? 0;
    $dfProg  = $defectStats['in_progress'] ?? 0;
    $dfRes   = ($defectStats['resolved'] ?? 0) + ($defectStats['verified'] ?? 0);
    $dfClosed= $defectStats['closed'] ?? 0;

    // ---- Resolved chart colors ----
    $colorBudgetActual = $budget['actual'] > $budget['current'] ? '#BC4749' : '#1E6B58';
    $cpiVal = $evm['cpi'] ?? 0;
    $spiVal = $evm['spi'] ?? 0;
    $colorCPI = $cpiVal >= 1 ? '#1E6B58' : ($cpiVal >= 0.9 ? '#D4A373' : '#BC4749');
    $colorSPI = $spiVal >= 1 ? '#1E6B58' : ($spiVal >= 0.9 ? '#D4A373' : '#BC4749');
    $eacVal   = $evm['eac'] ?? $evm['bac'];
    $colorEAC = $eacVal > $evm['bac'] ? '#BC4749' : '#1E6B58';
@endphp

{{-- Header --}}
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h3 class="mb-1">{{ $project->name }}</h3>
        @include('partials.badge', ['value' => $project->status])
        <span class="text-muted small ms-2">Status date {{ $asOf->format('d M Y') }}</span>
    </div>
    <a href="{{ route('report', $project) }}" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Full report</a>
</div>

{{-- ============ ROW 1: 4 compact KPI cards ============ --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card {{ $progressClass }} h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Overall Progress</div>
                        <div class="stat-value">{{ number_format($progress['complete'], 1) }}<span class="fs-6 text-muted">%</span></div>
                        <div class="kpi-sub">Planned {{ number_format($progress['planned'], 1) }}% · time {{ number_format($progress['time_elapsed'], 0) }}%</div>
                    </div>
                    <i class="bi bi-graph-up-arrow kpi-icon text-success"></i>
                </div>
                <div class="kpi-chart">
                    <canvas id="chartProgress" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card {{ $budgetClass }} h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Budget Used</div>
                        <div class="stat-value money">{{ number_format($budget['used_pct'], 1) }}<span class="fs-6 text-muted">%</span></div>
                        <div class="kpi-sub">PKR {{ number_format($budget['actual']) }} of {{ number_format($budget['current']) }}</div>
                    </div>
                    <i class="bi bi-cash-coin kpi-icon text-primary"></i>
                </div>
                <div class="kpi-chart">
                    <canvas id="chartBudget" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card {{ $deadlineClass }} h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Deadline</div>
                        <div class="stat-value fs-4">{{ $deadline['current']->format('d M Y') }}</div>
                        <div class="kpi-sub {{ $analysis['deadline_slip_days'] > 0 ? 'text-danger' : '' }}">
                            Projected {{ $analysis['projected_finish']->format('d M Y') }}
                            @if($analysis['deadline_slip_days'] > 0) · +{{ $analysis['deadline_slip_days'] }}d late @else · on time @endif
                        </div>
                    </div>
                    <i class="bi bi-flag kpi-icon text-warning"></i>
                </div>
                <div class="kpi-chart">
                    <canvas id="chartDeadline" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card {{ $criticalDefects->count() ? 'bad' : 'good' }} h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Critical Defects</div>
                        <div class="stat-value">{{ $criticalDefects->count() }}</div>
                        <div class="kpi-sub">{{ $defectStats->except(['closed','verified','resolved'])->sum() }} unresolved in total</div>
                    </div>
                    <i class="bi bi-bug kpi-icon text-danger"></i>
                </div>
                <div class="kpi-chart d-flex align-items-center justify-content-center">
                    <canvas id="chartDefects" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============ ROW 2: 4 more compact KPI cards ============ --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card {{ $cpiBad ? 'bad' : 'good' }} h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">CPI · Cost Performance</div>
                        <div class="stat-value">{{ $evm['cpi'] !== null ? number_format($evm['cpi'], 2) : '—' }}</div>
                        <div class="kpi-sub">{{ $evm['cost_status'] }} · threshold {{ number_format($thresholds['cpi'], 2) }}</div>
                    </div>
                    <i class="bi bi-speedometer2 kpi-icon text-primary"></i>
                </div>
                <div class="kpi-chart d-flex align-items-center justify-content-center">
                    <canvas id="chartCPI" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card {{ $spiBad ? 'bad' : 'good' }} h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">SPI · Schedule Performance</div>
                        <div class="stat-value">{{ $evm['spi'] !== null ? number_format($evm['spi'], 2) : '—' }}</div>
                        <div class="kpi-sub">{{ $evm['schedule_status'] }} · threshold {{ number_format($thresholds['spi'], 2) }}</div>
                    </div>
                    <i class="bi bi-clock-history kpi-icon text-warning"></i>
                </div>
                <div class="kpi-chart d-flex align-items-center justify-content-center">
                    <canvas id="chartSPI" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">PV · EV · AC</div>
                        <div class="stat-value money fs-5">PKR {{ number_format($evm['ev'] / 1000) }}k</div>
                        <div class="kpi-sub">PV {{ number_format($evm['pv'] / 1000) }}k · AC {{ number_format($evm['ac'] / 1000) }}k</div>
                    </div>
                    <i class="bi bi-bar-chart kpi-icon text-primary"></i>
                </div>
                <div class="kpi-chart">
                    <canvas id="chartEVM" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card {{ ($evm['vac'] ?? 0) < 0 ? 'bad' : 'good' }} h-100 kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Forecast (EAC)</div>
                        <div class="stat-value money fs-5">{{ $evm['eac'] !== null ? 'PKR ' . number_format($evm['eac'] / 1000) . 'k' : '—' }}</div>
                        <div class="kpi-sub">
                            VAC {{ $evm['vac'] !== null ? ($evm['vac'] < 0 ? '-' : '+') . 'PKR ' . number_format(abs($evm['vac']) / 1000) . 'k' : '—' }}
                        </div>
                    </div>
                    <i class="bi bi-bullseye kpi-icon text-success"></i>
                </div>
                <div class="kpi-chart">
                    <canvas id="chartEAC" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============ COLLAPSIBLE WARNINGS PANEL ============ --}}
@if(count($warnings))
    <div class="accordion mb-4" id="warningsAccordion">
        <div class="accordion-item alert-panel border-0 shadow-sm">
            <h2 class="accordion-header" id="headingWarnings">
                <button class="accordion-button collapsed alert-panel-header d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWarnings" aria-expanded="false" aria-controls="collapseWarnings">
                    <span class="d-flex align-items-center">
                        <i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>
                        <span class="fw-bold text-dark me-2">System Alerts & Warnings</span>
                        <span class="badge bg-light text-muted border rounded-pill">{{ count($warnings) }}</span>
                    </span>
                </button>
            </h2>
            <div id="collapseWarnings" class="accordion-collapse collapse" aria-labelledby="headingWarnings" data-bs-parent="#warningsAccordion">
                <div class="accordion-body p-0">
                    <ul class="list-group list-group-flush alert-list">
                        @foreach($warnings as $w)
                            @php
                                $levelColor = $w['severity'] === 'critical' ? 'danger' : ($w['severity'] === 'warning' ? 'warning' : 'info');
                                $icon = $w['severity'] === 'critical' ? 'bi-x-circle-fill' : ($w['severity'] === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
                            @endphp
                            <li class="list-group-item d-flex align-items-start gap-3 py-3 alert-{{ $levelColor }}">
                                <i class="bi {{ $icon }} fs-5 mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark text-capitalize mb-1" style="font-size: 0.85rem; letter-spacing: .3px;">
                                        {{ str_replace('_', ' ', $w['type']) }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.9rem;">{{ $w['message'] }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ============ DETAILED LISTS ============ --}}
<div class="row g-3">
    <div class="col-xl-6">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-arrow-left-right"></i> Pending change requests</span>
                <a href="{{ route('change-requests.index', $project) }}" class="small">Open</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>CR</th><th>Title</th><th class="text-end">Cost</th><th class="text-end">Days</th></tr></thead>
                <tbody>
                @forelse($pendingChangeRequests as $cr)
                    <tr><td class="fw-semibold">{{ $cr->code }}</td><td>{{ $cr->title }}</td>
                        <td class="text-end money">+{{ number_format($cr->cost_impact) }}</td><td class="text-end">+{{ $cr->schedule_impact_days }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No pending change requests.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-exclamation-triangle"></i> Delayed activities</span>
                <a href="{{ route('activities.index', $project) }}" class="small">Open</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Activity</th><th>Delay</th><th>Pushes out</th></tr></thead>
                <tbody>
                @forelse($delayedActivities as $d)
                    <tr><td><strong>{{ $d['activity']->code }}</strong> {{ \Illuminate\Support\Str::limit($d['activity']->name, 32) }}</td>
                        <td><span class="badge text-bg-danger">+{{ $d['delay_days'] }}d</span></td>
                        <td class="small">{{ collect($d['impacted'])->pluck('code')->implode(', ') ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">No delayed activities.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-bug"></i> Unresolved critical defects</span>
                <a href="{{ route('defects.index', $project) }}" class="small">Open</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Defect</th><th>Assigned</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($criticalDefects as $d)
                    <tr><td class="fw-semibold">{{ $d->code }}</td><td>{{ $d->title }}</td><td class="small">{{ $d->assignee?->name ?? 'Unassigned' }}</td>
                        <td>@include('partials.badge', ['value' => $d->status])</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No unresolved critical defects.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-flag"></i> Milestones</div>
            <ul class="list-group list-group-flush">
                @forelse($milestones as $ms)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $ms->title }}<div class="small text-muted">Due {{ $ms->due_date->format('d M Y') }} · {{ $ms->responsible?->name ?? 'unassigned' }}</div></span>
                        @include('partials.badge', ['value' => $ms->state])
                    </li>
                @empty
                    <li class="list-group-item text-muted">No milestones defined.</li>
                @endforelse
            </ul>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-diagram-2"></i> Scope, tasks and quality</div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-4"><div class="stat-label">Requirements</div>
                        <div>@foreach(['approved' => 'success', 'proposed' => 'secondary', 'rejected' => 'danger', 'deferred' => 'warning'] as $k => $c)
                            <span class="badge text-bg-{{ $c }} {{ $c === 'warning' ? 'text-dark' : '' }}" title="{{ $k }}">{{ $requirementStats[$k] ?? 0 }}</span>@endforeach</div>
                        <div class="small text-muted">approved / proposed / rejected / deferred</div></div>
                    <div class="col-4"><div class="stat-label">Activities</div>
                        <div>@foreach(['completed' => 'success', 'in_progress' => 'primary', 'delayed' => 'danger', 'not_started' => 'secondary'] as $k => $c)
                            <span class="badge text-bg-{{ $c }}" title="{{ $k }}">{{ $activityStats[$k] ?? 0 }}</span>@endforeach</div>
                        <div class="small text-muted">done / active / delayed / waiting</div></div>
                    <div class="col-4"><div class="stat-label">Defects</div>
                        <div>@foreach(['open' => 'danger', 'in_progress' => 'warning', 'resolved' => 'info', 'closed' => 'success'] as $k => $c)
                            <span class="badge text-bg-{{ $c }} {{ $c === 'warning' ? 'text-dark' : '' }}" title="{{ $k }}">{{ $defectStats[$k] ?? 0 }}</span>@endforeach</div>
                        <div class="small text-muted">open / active / resolved / closed</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-shield-exclamation"></i> Top risks</span>
                <a href="{{ route('risks.index', $project) }}" class="small">Open</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <tbody>
                @forelse($topRisks as $r)
                    <tr><td class="fw-semibold">{{ $r->code }}</td><td>{{ $r->title }}</td><td class="text-center">{{ $r->score }}</td><td>@include('partials.badge', ['value' => $r->level])</td></tr>
                @empty
                    <tr><td class="text-center text-muted py-3">No open risks.</td></tr>
                @endforelse
                </tbody></table></div>
        </div>

        <div class="card mb-3 {{ $errors_ ? 'border-danger border-start border-4' : '' }}">
            <div class="card-header"><i class="bi bi-patch-check"></i> Consistency checks across modules</div>
            <ul class="list-group list-group-flush">
                @forelse($consistency as $c)
                    <li class="list-group-item small"><i class="bi {{ $c['level'] === 'error' ? 'bi-x-circle-fill text-danger' : 'bi-exclamation-circle text-warning' }}"></i> {{ $c['message'] }}</li>
                @empty
                    <li class="list-group-item small text-success"><i class="bi bi-check-circle-fill"></i> Requirements, WBS, activities, costs, change requests and quality records are consistent.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
    /* Compact KPI cards */
    .kpi-card { overflow: hidden; }
    .kpi-card .card-body { padding: .9rem 1rem; display: flex; flex-direction: column; height: 100%; }
    .kpi-card .stat-value { font-size: 1.9rem; font-weight: 800; line-height: 1.05; letter-spacing: -.5px; }
    .kpi-card .stat-label { font-size: .68rem; letter-spacing: 1px; color: #748D92; }
    .kpi-card .kpi-sub { font-size: .72rem; color: #748D92; margin-top: .15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kpi-card .kpi-icon { font-size: 1.3rem; opacity: .7; }
    .kpi-card .kpi-chart { margin-top: auto; padding-top: .4rem; height: 58px; position: relative; }
    .kpi-card .kpi-chart canvas { max-height: 58px; }

    /* Collapsible Alert Panel */
    .alert-panel { border: 1px solid #E5EAE8; border-radius: .65rem; overflow: hidden; background: #fff; }
    .alert-panel-header { background: #EAF0EE !important; padding: .8rem 1.25rem; border: none; border-bottom: 1px solid transparent; box-shadow: none !important; }
    .alert-panel-header:not(.collapsed) { border-bottom-color: #E5EAE8; }
    
    /* Customize the Bootstrap accordion arrow */
    .accordion-button::after {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23212A31'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        transition: transform .2s ease-in-out;
    }
    
    .alert-list .list-group-item { border-color: #E5EAE8; background: #fff; }
    .alert-list .list-group-item:last-child { border-bottom: 0; }
    
    /* Subtle tinting for warning rows */
    .alert-list .alert-danger { background-color: #FCF7F7; border-left: 3px solid #BC4749; }
    .alert-list .alert-warning { background-color: #FBF8F4; border-left: 3px solid #D4A373; }
    .alert-list .alert-info { background-color: #F4F7F6; border-left: 3px solid #124E66; }
    
    .alert-list .alert-danger i { color: #BC4749; }
    .alert-list .alert-warning i { color: #D4A373; }
    .alert-list .alert-info i { color: #124E66; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.font.size = 10;
    Chart.defaults.color = '#748D92';

    const BRAND = '#124E66';
    const GOOD = '#1E6B58';
    const WARN = '#D4A373';
    const BAD  = '#BC4749';
    const NEUTRAL = '#C5CECE';

    const sparkPV = @json($sparkPV);
    const sparkLabels = @json($sparkLabels);

    // 1. Progress
    new Chart(document.getElementById('chartProgress'), {
        type: 'line',
        data: { labels: sparkLabels, datasets: [{ data: sparkPV, borderColor: GOOD, backgroundColor: 'rgba(30,107,88,.15)', fill: true, tension: .35, pointRadius: 0, borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } } }
    });

    // 2. Budget
    new Chart(document.getElementById('chartBudget'), {
        type: 'bar',
        data: { labels: ['Planned', 'Current', 'Actual'], datasets: [{ data: [{{ $budget['planned'] }}, {{ $budget['current'] }}, {{ $budget['actual'] }}], backgroundColor: [NEUTRAL, BRAND, "{{ $colorBudgetActual }}"], borderRadius: 3, barThickness: 14 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } } }
    });

    // 3. Deadline
    const elapsedDays = {{ max(0, $progress['time_elapsed']) }};
    new Chart(document.getElementById('chartDeadline'), {
        type: 'bar',
        data: { labels: ['Timeline'], datasets: [{ label: 'Elapsed', data: [elapsedDays], backgroundColor: BRAND, borderRadius: 3, barThickness: 14 }, { label: 'Remaining', data: [Math.max(0, 100 - elapsedDays)], backgroundColor: NEUTRAL, borderRadius: 3, barThickness: 14 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false, stacked: true }, y: { display: false, stacked: true } } }
    });

    // 4. Defects
    new Chart(document.getElementById('chartDefects'), {
        type: 'doughnut',
        data: { labels: ['Open', 'In progress', 'Resolved', 'Closed'], datasets: [{ data: [{{ $dfOpen }}, {{ $dfProg }}, {{ $dfRes }}, {{ $dfClosed }}], backgroundColor: [BAD, WARN, '#748D92', GOOD], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    // 5. CPI
    const cpi = {{ $cpiVal }};
    new Chart(document.getElementById('chartCPI'), {
        type: 'doughnut',
        data: { labels: ['CPI', ''], datasets: [{ data: [Math.min(2, Math.max(0, cpi)), Math.max(0, 2 - Math.min(2, cpi))], backgroundColor: ["{{ $colorCPI }}", NEUTRAL], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', circumference: 180, rotation: 270, plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    // 6. SPI
    const spi = {{ $spiVal }};
    new Chart(document.getElementById('chartSPI'), {
        type: 'doughnut',
        data: { labels: ['SPI', ''], datasets: [{ data: [Math.min(2, Math.max(0, spi)), Math.max(0, 2 - Math.min(2, spi))], backgroundColor: ["{{ $colorSPI }}", NEUTRAL], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', circumference: 180, rotation: 270, plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    // 7. PV / EV / AC
    new Chart(document.getElementById('chartEVM'), {
        type: 'bar',
        data: { labels: ['PV', 'EV', 'AC'], datasets: [{ data: [{{ $evm['pv'] }}, {{ $evm['ev'] }}, {{ $evm['ac'] }}], backgroundColor: [BRAND, GOOD, BAD], borderRadius: 3, barThickness: 14 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } } }
    });

    // 8. Forecast EAC
    const bac = {{ $evm['bac'] }};
    const eac = {{ $eacVal }};
    new Chart(document.getElementById('chartEAC'), {
        type: 'bar',
        data: { labels: ['BAC', 'EAC'], datasets: [{ data: [bac, eac], backgroundColor: [NEUTRAL, "{{ $colorEAC }}"], borderRadius: 3, barThickness: 12 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } } }
    });
</script>
@endpush