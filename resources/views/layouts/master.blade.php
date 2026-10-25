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
        :root {
            --sidebar-w: 268px;
            /* Deep nocturnal slate */
            --sidebar-bg: #212A31;
            --sidebar-bg-2: #2E3944;
            
            /* Sidebar text */
            --sidebar-fg: #D3D9D4;
            --sidebar-fg-muted: #748D92;
            
            /* Oceanic deep teal accent */
            --sidebar-active: #124E66;
            --brand: #124E66;
            
            /* Crisp moonlit frost white */
            --page-bg: #D3D9D4;

            /* Semantic */
            --good: #1E6B58; /* Clean Slate-Teal Green */
            --warn: #D4A373; /* Muted Amber/Coral */
            --bad:  #BC4749; /* Subdued Cinematic Crimson */

            /* Neutrals */
            --border-base: #C5CECE;
            --border-soft: #E5EAE8;
            --surface-tint: #EAF0EE;
            --text-main: #212A31;
            --text-muted: #748D92;
        }

        * { box-sizing: border-box; }
        body { background: var(--page-bg); margin: 0; font-size: .93rem; color: var(--text-main); }

        /* Bootstrap overrides — Moonlit Tech brand */
        a { color: var(--brand); }
        a:hover { color: #0E3B4D; }
        .btn-primary { --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand);
                       --bs-btn-hover-bg: #0E3B4D; --bs-btn-hover-border-color: #0E3B4D;
                       --bs-btn-active-bg: #092936; --bs-btn-active-border-color: #092936;
                       --bs-btn-focus-shadow-rgb: 18,78,102; }
        .btn-outline-primary { --bs-btn-color: var(--brand); --bs-btn-border-color: var(--brand);
                               --bs-btn-hover-bg: var(--brand); --bs-btn-hover-border-color: var(--brand);
                               --bs-btn-active-bg: var(--brand); --bs-btn-active-border-color: var(--brand);
                               --bs-btn-focus-shadow-rgb: 18,78,102; }
        .btn-success { --bs-btn-bg: var(--good); --bs-btn-border-color: var(--good);
                       --bs-btn-hover-bg: #15503F; --bs-btn-hover-border-color: #15503F; }
        .btn-outline-success { --bs-btn-color: var(--good); --bs-btn-border-color: var(--good);
                               --bs-btn-hover-bg: var(--good); --bs-btn-hover-border-color: var(--good); }
        .btn-outline-danger { --bs-btn-color: var(--bad); --bs-btn-border-color: var(--bad);
                              --bs-btn-hover-bg: var(--bad); --bs-btn-hover-border-color: var(--bad); }
        .btn-outline-secondary { --bs-btn-color: #748D92; --bs-btn-border-color: #C5CECE;
                                 --bs-btn-hover-bg: #748D92; --bs-btn-hover-border-color: #748D92;
                                 --bs-btn-hover-color: #fff; }
        .form-control:focus, .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 .2rem rgba(18,78,102,.25); }
        .text-primary { color: var(--brand) !important; }
        .bg-primary { background-color: var(--brand) !important; }
        
        /* Alerts */
        .alert-success { background-color: #E3F0EC; border-color: #B3D4C9; color: #1E6B58; }
        .alert-warning { background-color: #F9F1E6; border-color: #EBD3B8; color: #8C5A27; }
        .alert-danger  { background-color: #F5E6E7; border-color: #E0B6B7; color: #8A2F31; }
        .alert-info    { background-color: #EAF0EE; border-color: #C5CECE; color: #124E66; }

        /* ---------- Layout shell ---------- */
        .app-shell { display: flex; min-height: 100vh; }

        /* ---------- Sidebar ---------- */
        .app-sidebar {
            width: var(--sidebar-w); flex: 0 0 var(--sidebar-w);
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, var(--sidebar-bg-2) 100%);
            color: var(--sidebar-fg);
            position: fixed; top: 0; left: 0; bottom: 0;
            display: flex; flex-direction: column;
            overflow-y: auto; z-index: 1040;
            transition: transform .25s ease;
        }
        .app-sidebar .brand {
            padding: 1.1rem 1.25rem; font-weight: 700; font-size: 1.15rem;
            color: #fff; letter-spacing: .3px;
            border-bottom: 1px solid rgba(211,217,212,.1);
            display: flex; align-items: center; gap: .55rem;
            position: sticky; top: 0; background: var(--sidebar-bg); z-index: 2;
        }
        .app-sidebar .brand i { color: #748D92; font-size: 1.4rem; }

        .side-section-label {
            font-size: .68rem; text-transform: uppercase; letter-spacing: 1px;
            color: var(--sidebar-fg-muted); padding: .9rem 1.25rem .35rem;
            font-weight: 600;
        }

        .side-nav { list-style: none; padding: 0; margin: 0; }
        .side-nav a {
            display: flex; align-items: center; gap: .7rem;
            padding: .55rem 1.25rem; color: var(--sidebar-fg);
            text-decoration: none; font-size: .86rem;
            border-left: 3px solid transparent;
            transition: background .15s, color .15s, border-color .15s;
        }
        .side-nav a i { font-size: 1.05rem; width: 1.25rem; text-align: center; opacity: .85; }
        .side-nav a:hover { background: rgba(116,141,146,.15); color: #fff; }
        .side-nav a.active {
            background: rgba(18,78,102,.35);
            color: #fff; border-left-color: var(--brand); font-weight: 600;
        }
        .side-nav a.active i { opacity: 1; color: #D3D9D4; }

        .side-project-meta {
            margin: 0 1rem 1rem; padding: .8rem .9rem;
            background: rgba(211,217,212,.05); border-radius: .6rem;
            font-size: .78rem; color: var(--sidebar-fg-muted);
        }
        .side-project-meta strong { color: #fff; display: block; font-size: .86rem; margin-bottom: .25rem; }

        .side-footer {
            margin-top: auto; padding: 1rem 1.25rem;
            border-top: 1px solid rgba(211,217,212,.1);
            font-size: .8rem; color: var(--sidebar-fg-muted);
        }

        /* ---------- Main content ---------- */
        .app-main {
            flex: 1 1 auto; margin-left: var(--sidebar-w);
            display: flex; flex-direction: column; min-width: 0;
        }

        .app-topbar {
            background: #fff; border-bottom: 1px solid var(--border-base);
            padding: .6rem 1.4rem;
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap;
            position: sticky; top: 0; z-index: 1030;
        }
        .app-topbar .hamburger { display: none; }
        .app-topbar .status-form { display: flex; align-items: center; gap: .4rem; }
        .app-topbar .status-form .form-label { margin: 0; font-size: .72rem; color: var(--text-muted); white-space: nowrap; }

        .app-content { padding: 1.4rem 1.4rem 2rem; flex: 1 1 auto; }

        /* ---------- Cards / stats ---------- */
        .card { border: 0; box-shadow: 0 1px 3px rgba(33,42,49,.08); border-radius: .65rem; background: #fff; }
        .card-header { background: #fff; font-weight: 600; border-bottom: 1px solid var(--border-soft);
                       border-radius: .65rem .65rem 0 0 !important; color: var(--text-main); }

        .stat-card { border-left: 4px solid var(--brand); }
        .stat-card.good { border-left-color: var(--good); }
        .stat-card.warn { border-left-color: var(--warn); }
        .stat-card.bad  { border-left-color: var(--bad); }
        .stat-value { font-size: 1.55rem; font-weight: 700; line-height: 1.15; color: var(--text-main); }
        .stat-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); }

        table.table td, table.table th { vertical-align: middle; }
        table.table thead.table-light th { background: var(--surface-tint) !important; color: var(--text-main); border-color: var(--border-soft); }
        .table-sm td, .table-sm th { font-size: .85rem; }
        .form-label { font-size: .8rem; font-weight: 600; color: var(--text-muted); margin-bottom: .2rem; }
        .money { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .muted-row > td { background: #F8FAF9; }

        /* ---------- Gantt ---------- */
        .g-wrap { overflow-x: auto; }
        .g-inner { min-width: 980px; }
        .g-row { display: flex; border-bottom: 1px solid var(--border-soft); }
        .g-label { width: 270px; flex: none; padding: 5px 10px; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .g-track { position: relative; flex: 1; height: 34px;
            background-image: linear-gradient(to right, var(--border-soft) 1px, transparent 1px); background-size: var(--week, 7%) 100%; }
        .g-head .g-track { height: 30px; background: var(--surface-tint); }
        .g-tick { position: absolute; top: 6px; font-size: .68rem; color: var(--text-muted); transform: translateX(2px); }
        .g-bar { position: absolute; top: 7px; height: 20px; border-radius: 4px; overflow: hidden; color: #fff; font-size: .68rem; line-height: 20px; padding-left: 5px; white-space: nowrap; }
        .g-bar .g-fill { position: absolute; left: 0; top: 0; bottom: 0; background: rgba(0,0,0,.25); }
        .g-bar span { position: relative; }
        .bar-normal { background: var(--brand); }
        .bar-critical { background: var(--bad); }
        .bar-delayed { background: var(--warn); }
        .bar-done { background: var(--good); }
        .g-overrun { position: absolute; top: 7px; height: 20px; border-radius: 0 4px 4px 0;
            background: repeating-linear-gradient(45deg, var(--warn), var(--warn) 4px, #F4E3D3 4px, #F4E3D3 8px); }
        .g-line { position: absolute; top: 0; bottom: 0; width: 2px; z-index: 2; }
        .g-line.status { background: var(--brand); }
        .g-line.deadline { background: var(--bad); }
        .g-ms { position: absolute; top: 9px; width: 16px; height: 16px; transform: rotate(45deg) translateX(-8px); transform-origin: left top; }
        .ms-achieved { background: var(--good); } .ms-on_track { background: var(--brand); }
        .ms-at_risk { background: var(--warn); } .ms-missed { background: var(--bad); }

        /* ---------- Risk matrix ---------- */
        .matrix td { width: 16%; height: 86px; vertical-align: top; font-size: .74rem; padding: 4px; border: 2px solid #fff; }
        .matrix td.axis { width: 4%; background: transparent; font-weight: 700; text-align: center; vertical-align: middle; color: var(--text-muted); }
        .cell-low { background: #D1E7E1; } .cell-medium { background: #F2ECD8; }
        .cell-high { background: #F4D7C9; } .cell-extreme { background: #E6B8B9; }
        .risk-chip { display: inline-block; background: #fff; border-radius: 4px; padding: 0 5px; margin: 1px; font-weight: 600; border: 1px solid rgba(0,0,0,.15); }

        /* ---------- Trees ---------- */
        ul.tree { list-style: none; padding-left: 1.4rem; border-left: 2px dashed #C5CECE; margin: .25rem 0 .25rem .6rem; }
        ul.tree.root { border-left: 0; padding-left: 0; margin-left: 0; }
        .tree-node { background: #fff; border: 1px solid var(--border-base); border-radius: 6px; padding: 6px 10px; margin: 4px 0; }
        .pdpc-problem { border-left: 4px solid var(--bad); } .pdpc-preventive_action { border-left: 4px solid var(--good); }
        .pdpc-corrective_action { border-left: 4px solid var(--brand); } .pdpc-contingency { border-left: 4px solid #6f42c1; }

        /* ---------- Chart helper ---------- */
        .chart-box { position: relative; width: 100%; }
        .chart-box canvas { width: 100% !important; }

        /* ---------- Responsive ---------- */
        @media (max-width: 991.98px) {
            .app-sidebar { transform: translateX(-100%); }
            .app-sidebar.open { transform: translateX(0); box-shadow: 0 0 40px rgba(0,0,0,.4); }
            .app-main { margin-left: 0; }
            .app-topbar .hamburger { display: inline-flex; }
            .sidebar-backdrop { position: fixed; inset: 0; background: rgba(33,42,49,.6); z-index: 1035; display: none; }
            .sidebar-backdrop.show { display: block; }
        }

        @media print {
            .app-sidebar, .app-topbar, .no-print, .btn, form.inline-form { display: none !important; }
            .app-main { margin-left: 0; }
            body { background: #fff; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
        .badge-success   { background:#1E6B58 !important; color:#fff; }
.badge-warning   { background:#D4A373 !important; color:#212A31; }
.badge-danger    { background:#BC4749 !important; color:#fff; }
.badge-secondary { background:#748D92 !important; color:#fff; }
.badge-info      { background:#124E66 !important; color:#fff; }
    </style>
    @stack('head')
</head>
<body>
@php
    $hasProject = isset($project) && $project instanceof \App\Models\Project;
    $statusDateValue = session('status_date', now()->toDateString());

    $sideGroups = [
        'Overview' => [
            ['projects.index', 'All Projects', 'bi-grid-1x2', null],
        ],
        'Initiation & Integration' => [
            ['projects.show', 'Charter & Plan', 'bi-file-earmark-text', $hasProject],
            ['dashboard', 'Dashboard', 'bi-speedometer2', $hasProject],
        ],
        'Scope' => [
            ['requirements.index', 'Requirements', 'bi-list-check', $hasProject],
            ['wbs.index', 'WBS', 'bi-diagram-3', $hasProject],
            ['change-requests.index', 'Change Requests', 'bi-arrow-left-right', $hasProject],
        ],
        'Schedule' => [
            ['activities.index', 'Activities', 'bi-kanban', $hasProject],
            ['schedule.gantt', 'Gantt', 'bi-bar-chart-steps', $hasProject],
        ],
        'Cost & EVM' => [
            ['costs.index', 'Costs', 'bi-cash-coin', $hasProject],
            ['evm.index', 'EVM', 'bi-graph-up-arrow', $hasProject],
        ],
        'Quality & Risk' => [
            ['defects.index', 'Defects', 'bi-bug', $hasProject],
            ['quality.index', 'Quality', 'bi-clipboard-check', $hasProject],
            ['risks.index', 'Risks', 'bi-exclamation-triangle', $hasProject],
            ['pdpc.index', 'PDPC', 'bi-signpost-split', $hasProject],
        ],
        'Reporting' => [
            ['report', 'Report', 'bi-printer', $hasProject],
        ],
    ];
@endphp

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <a href="{{ route('projects.index') }}" class="brand text-decoration-none">
            <i class="bi bi-clipboard-data"></i> Software PMS
        </a>

        @if($hasProject)
            <div class="side-project-meta mt-3">
                <strong>{{ \Illuminate\Support\Str::limit($project->name, 42) }}</strong>
                <div class="d-flex gap-1 flex-wrap mt-1">
                    @include('partials.badge', ['value' => $project->status])
                    @if($project->charter_approved)
                        <span class="badge text-bg-success">Charter</span>
                    @else
                        <span class="badge text-bg-warning text-dark">Draft</span>
                    @endif
                </div>
            </div>
        @endif

        @foreach($sideGroups as $groupLabel => $links)
            @php $visible = array_filter($links, fn ($l) => $l[3] !== false); @endphp
            @if(count($visible))
                <div class="side-section-label">{{ $groupLabel }}</div>
                <ul class="side-nav">
                    @foreach($links as [$routeName, $label, $icon, $needsProject])
                        @continue($needsProject === false)
                        @php $url = $needsProject ? route($routeName, $project) : route($routeName); @endphp
                        <li>
                            <a href="{{ $url }}" class="{{ request()->routeIs($routeName) ? 'active' : '' }}">
                                <i class="bi {{ $icon }}"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach

        <div class="side-footer">
            @auth
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="text-truncate">{{ auth()->user()->name }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button class="btn btn-outline-light btn-sm w-100"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            @endauth
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary hamburger" id="sidebarToggle" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <span class="fw-semibold d-none d-md-inline">@yield('title', 'Dashboard')</span>
            </div>

            @if($hasProject)
                <form method="POST" action="{{ route('status-date.set') }}" class="status-form inline-form">
                    @csrf
                    <label class="form-label" title="EVM, delays and progress are measured at this date">Status date</label>
                    <input type="date" name="status_date" value="{{ $statusDateValue }}" class="form-control form-control-sm" style="width:150px">
                    <button class="btn btn-sm btn-outline-primary">Set</button>
                    @if(session()->has('status_date'))
                        <button class="btn btn-sm btn-outline-secondary" name="status_date" value="" title="Back to today">Reset</button>
                    @endif
                </form>
            @endif
        </header>

        <main class="app-content">
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('submit', function (e) {
        const msg = e.target.getAttribute('data-confirm');
        if (msg && !confirm(msg)) { e.preventDefault(); }
    });
    (function () {
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggle = document.getElementById('sidebarToggle');
        if (!sidebar || !toggle) return;
        const open = () => { sidebar.classList.add('open'); backdrop.classList.add('show'); };
        const close = () => { sidebar.classList.remove('open'); backdrop.classList.remove('show'); };
        toggle.addEventListener('click', () => sidebar.classList.contains('open') ? close() : open());
        backdrop.addEventListener('click', close);
    })();
</script>
@stack('scripts')
</body>
</html>