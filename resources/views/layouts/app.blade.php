<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Software PMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6fa; }
        .navbar-brand { font-weight: 700; letter-spacing: .2px; }
        .project-tabs { background: #fff; border-bottom: 1px solid #e3e6ee; }
        .project-tabs .nav-link { color: #4a5568; font-size: .88rem; padding: .7rem .8rem; border-bottom: 3px solid transparent; }
        .project-tabs .nav-link.active { color: #0d6efd; border-bottom-color: #0d6efd; font-weight: 600; background: transparent; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(16, 24, 40, .08); }
        .card-header { background: #fff; font-weight: 600; border-bottom: 1px solid #eef0f5; }
        .stat-card { border-left: 4px solid #0d6efd; }
        .stat-card.good { border-left-color: #198754; }
        .stat-card.warn { border-left-color: #ffc107; }
        .stat-card.bad { border-left-color: #dc3545; }
        .stat-value { font-size: 1.55rem; font-weight: 700; line-height: 1.15; }
        .stat-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        table.table td, table.table th { vertical-align: middle; }
        .table-sm td, .table-sm th { font-size: .86rem; }
        .form-label { font-size: .8rem; font-weight: 600; color: #4a5568; margin-bottom: .2rem; }
        .money { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .muted-row > td { background: #f8f9fc; }

        /* Gantt */
        .g-wrap { overflow-x: auto; }
        .g-inner { min-width: 980px; }
        .g-row { display: flex; border-bottom: 1px solid #eef0f5; }
        .g-label { width: 270px; flex: none; padding: 5px 10px; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .g-track { position: relative; flex: 1; height: 34px;
            background-image: linear-gradient(to right, #eef0f5 1px, transparent 1px); background-size: var(--week, 7%) 100%; }
        .g-head .g-track { height: 30px; background: #f8f9fc; }
        .g-tick { position: absolute; top: 6px; font-size: .68rem; color: #6b7280; transform: translateX(2px); }
        .g-bar { position: absolute; top: 7px; height: 20px; border-radius: 4px; overflow: hidden; color: #fff; font-size: .68rem; line-height: 20px; padding-left: 5px; white-space: nowrap; }
        .g-bar .g-fill { position: absolute; left: 0; top: 0; bottom: 0; background: rgba(0, 0, 0, .28); }
        .g-bar span { position: relative; }
        .bar-normal { background: #4c8bf5; }
        .bar-critical { background: #d6336c; }
        .bar-delayed { background: #f08c00; }
        .bar-done { background: #2f9e44; }
        .g-overrun { position: absolute; top: 7px; height: 20px; border-radius: 0 4px 4px 0;
            background: repeating-linear-gradient(45deg, #f08c00, #f08c00 4px, #ffd8a8 4px, #ffd8a8 8px); }
        .g-line { position: absolute; top: 0; bottom: 0; width: 2px; z-index: 2; }
        .g-line.status { background: #0d6efd; }
        .g-line.deadline { background: #dc3545; }
        .g-ms { position: absolute; top: 9px; width: 16px; height: 16px; transform: rotate(45deg) translateX(-8px); transform-origin: left top; }
        .ms-achieved { background: #2f9e44; } .ms-on_track { background: #4c8bf5; }
        .ms-at_risk { background: #f08c00; } .ms-missed { background: #dc3545; }

        /* Risk matrix */
        .matrix td { width: 16%; height: 86px; vertical-align: top; font-size: .74rem; padding: 4px; border: 2px solid #fff; }
        .matrix td.axis { width: 4%; background: transparent; font-weight: 700; text-align: center; vertical-align: middle; color: #4a5568; }
        .cell-low { background: #d3f9d8; } .cell-medium { background: #fff3bf; }
        .cell-high { background: #ffd8a8; } .cell-extreme { background: #ffa8a8; }
        .risk-chip { display: inline-block; background: #fff; border-radius: 4px; padding: 0 5px; margin: 1px; font-weight: 600; border: 1px solid rgba(0,0,0,.15); }

        /* Trees (WBS / PDPC) */
        ul.tree { list-style: none; padding-left: 1.4rem; border-left: 2px dashed #ced4da; margin: .25rem 0 .25rem .6rem; }
        ul.tree.root { border-left: 0; padding-left: 0; margin-left: 0; }
        .tree-node { background: #fff; border: 1px solid #e3e6ee; border-radius: 6px; padding: 6px 10px; margin: 4px 0; }
        .pdpc-problem { border-left: 4px solid #dc3545; } .pdpc-preventive_action { border-left: 4px solid #198754; }
        .pdpc-corrective_action { border-left: 4px solid #0d6efd; } .pdpc-contingency { border-left: 4px solid #6f42c1; }

        @media print {
            .navbar, .project-tabs, .no-print, .btn, form.inline-form { display: none !important; }
            body { background: #fff; } .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
@php
    $hasProject = isset($project) && $project instanceof \App\Models\Project;
    $tabs = [
        ['projects.show', 'Charter & Plan', 'bi-file-earmark-text'],
        ['dashboard', 'Dashboard', 'bi-speedometer2'],
        ['requirements.index', 'Requirements', 'bi-list-check'],
        ['wbs.index', 'WBS', 'bi-diagram-3'],
        ['change-requests.index', 'Change Requests', 'bi-arrow-left-right'],
        ['activities.index', 'Activities', 'bi-kanban'],
        ['schedule.gantt', 'Gantt', 'bi-bar-chart-steps'],
        ['costs.index', 'Costs', 'bi-cash-coin'],
        ['evm.index', 'EVM', 'bi-graph-up-arrow'],
        ['defects.index', 'Defects', 'bi-bug'],
        ['quality.index', 'Quality', 'bi-clipboard-check'],
        ['risks.index', 'Risks', 'bi-exclamation-triangle'],
        ['pdpc.index', 'PDPC', 'bi-signpost-split'],
        ['report', 'Report', 'bi-printer'],
    ];
    $statusDateValue = session('status_date', now()->toDateString());
@endphp

<nav class="navbar navbar-expand navbar-dark bg-dark">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="{{ route('projects.index') }}"><i class="bi bi-clipboard-data"></i> Software PMS</a>
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="{{ route('projects.index') }}">Projects</a></li>
        </ul>
        @auth
            <span class="navbar-text me-3 small"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</button>
            </form>
        @endauth
    </div>
</nav>

@if($hasProject)
    <div class="project-tabs no-print">
        <div class="container-fluid px-4 d-flex flex-wrap align-items-center justify-content-between">
            <ul class="nav flex-wrap">
                @foreach($tabs as [$routeName, $label, $icon])
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs($routeName) ? 'active' : '' }}" href="{{ route($routeName, $project) }}">
                            <i class="bi {{ $icon }}"></i> {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('status-date.set') }}" class="d-flex align-items-center gap-2 inline-form py-1">
                @csrf
                <label class="form-label mb-0 small text-muted" title="EVM, delays and progress are measured at this date">Status date</label>
                <input type="date" name="status_date" value="{{ $statusDateValue }}" class="form-control form-control-sm" style="width:150px">
                <button class="btn btn-sm btn-outline-primary">Set</button>
                @if(session()->has('status_date'))
                    <button class="btn btn-sm btn-outline-secondary" name="status_date" value="" title="Back to today">Reset</button>
                @endif
            </form>
        </div>
    </div>
@endif

<main class="container-fluid px-4 py-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show"><i class="bi bi-exclamation-triangle"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <strong><i class="bi bi-x-octagon"></i> Please fix the following:</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Confirm before any destructive form marked with data-confirm.
    document.addEventListener('submit', function (e) {
        const msg = e.target.getAttribute('data-confirm');
        if (msg && !confirm(msg)) { e.preventDefault(); }
    });
</script>
@stack('scripts')
</body>
</html>
