@extends('layouts.master')
@section('title', 'Gantt chart')

@section('content')
@php
    $chartStart = $project->start_date->copy()->startOfDay();
    $ends = collect($gantt)->pluck('end')->filter()->map(fn ($d) => \Carbon\Carbon::parse($d));
    $chartEnd = collect([$project->current_deadline, $analysis['planned_finish'], $analysis['projected_finish']])->merge($ends)->max()->copy()->addDays(3);
    $span = max(1, (int) round($chartStart->diffInDays($chartEnd, false)) + 1);
    $pct = fn ($days) => round(max(0, $days) / $span * 100, 3);
    $offset = fn ($date) => (int) round($chartStart->diffInDays(\Carbon\Carbon::parse($date)->startOfDay(), false));

    $weekWidth = round(7 / $span * 100, 3);
    $statusOffset = $offset($asOf);
    $deadlineOffset = $offset($project->current_deadline);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Gantt chart <small class="text-muted fs-6">{{ $project->name }}</small></h3>
    <div class="small d-flex gap-3 flex-wrap">
        <span><span class="badge bar-normal">&nbsp;</span> Normal</span>
        <span><span class="badge bar-critical">&nbsp;</span> Critical path</span>
        <span><span class="badge bar-delayed">&nbsp;</span> Delayed</span>
        <span><span class="badge bar-done">&nbsp;</span> Completed</span>
        <span><span class="badge" style="background:#0d6efd">&nbsp;</span> Status date</span>
        <span><span class="badge" style="background:#dc3545">&nbsp;</span> Deadline</span>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Planned finish</div><div class="stat-value fs-4">{{ $analysis['planned_finish']->format('d M Y') }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ $analysis['deadline_slip_days'] > 0 ? 'bad' : 'good' }}"><div class="card-body">
        <div class="stat-label">Projected finish</div><div class="stat-value fs-4">{{ $analysis['projected_finish']->format('d M Y') }}</div>
        <div class="small {{ $analysis['deadline_slip_days'] > 0 ? 'text-danger' : 'text-muted' }}">
            {{ $analysis['deadline_slip_days'] > 0 ? $analysis['deadline_slip_days'] . ' day(s) late' : 'Within deadline' }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="stat-label">Deadline</div><div class="stat-value fs-4">{{ $project->current_deadline->format('d M Y') }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card {{ count($analysis['delays']) ? 'warn' : 'good' }}"><div class="card-body">
        <div class="stat-label">Delayed activities</div><div class="stat-value fs-4">{{ count($analysis['delays']) }}</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body g-wrap">
        @if(empty($gantt))
            <div class="text-center text-muted py-5">No activities yet. <a href="{{ route('activities.index', $project) }}">Add activities</a> to see the chart.</div>
        @else
        <div class="g-inner" style="--week: {{ $weekWidth }}%">
            <div class="g-row g-head">
                <div class="g-label fw-semibold">Activity</div>
                <div class="g-track">
                    @for($d = 0; $d < $span; $d += 7)
                        <span class="g-tick" style="left: {{ $pct($d) }}%">{{ $chartStart->copy()->addDays($d)->format('d M') }}</span>
                    @endfor
                </div>
            </div>

            @if($milestones->isNotEmpty())
                <div class="g-row" style="background:#fafbff">
                    <div class="g-label fw-semibold"><i class="bi bi-flag"></i> Milestones</div>
                    <div class="g-track">
                        @foreach($milestones as $ms)
                            <div class="g-ms ms-{{ $ms->state }}" style="left: {{ $pct($offset($ms->due_date)) }}%"
                                 title="{{ $ms->title }}: {{ $ms->due_date->format('d M Y') }} ({{ str_replace('_', ' ', $ms->state) }})"></div>
                        @endforeach
                        <div class="g-line status" style="left: {{ $pct($statusOffset) }}%"></div>
                        <div class="g-line deadline" style="left: {{ $pct($deadlineOffset) }}%"></div>
                    </div>
                </div>
            @endif

            @foreach($gantt as $row)
                @continue(!$row['start'] || !$row['end'])
                @php
                    $startOff = $offset($row['start']);
                    $len = $offset($row['end']) - $startOff + 1;
                @endphp
                <div class="g-row">
                    <div class="g-label" title="{{ $row['name'] }}{{ $row['assignee'] ? ' | ' . $row['assignee'] : '' }}">
                        {{ $row['name'] }}
                        @if($row['assignee'])<span class="text-muted">· {{ \Illuminate\Support\Str::of($row['assignee'])->before(' ') }}</span>@endif
                    </div>
                    <div class="g-track">
                        <div class="g-bar {{ $row['custom_class'] }}" style="left: {{ $pct($startOff) }}%; width: {{ $pct($len) }}%"
                             title="{{ $row['name'] }} | {{ $row['start'] }} to {{ $row['end'] }} | {{ $row['progress'] }}% | slack {{ $row['slack_days'] }}d{{ $row['delay_days'] ? ' | delayed ' . $row['delay_days'] . 'd' : '' }}">
                            <div class="g-fill" style="width: {{ $row['progress'] }}%"></div>
                            <span>{{ number_format($row['progress'], 0) }}%</span>
                        </div>
                        @if($row['delay_days'] > 0)
                            <div class="g-overrun" style="left: {{ $pct($startOff + $len) }}%; width: {{ $pct($row['delay_days']) }}%"
                                 title="Projected overrun: {{ $row['delay_days'] }} day(s)"></div>
                        @endif
                        <div class="g-line status" style="left: {{ $pct($statusOffset) }}%"></div>
                        <div class="g-line deadline" style="left: {{ $pct($deadlineOffset) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-table"></i> Schedule detail (forward / backward pass)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Activity</th><th>Early start</th><th>Early end</th><th>Late end</th><th class="text-center">Slack</th><th>Critical</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($activities as $a)
                        <tr>
                            <td><strong>{{ $a->code }}</strong> {{ \Illuminate\Support\Str::limit($a->name, 34) }}</td>
                            <td class="small">{{ $a->planned_start?->format('d M') }}</td>
                            <td class="small">{{ $a->planned_end?->format('d M') }}</td>
                            <td class="small">{{ $a->late_end?->format('d M') }}</td>
                            <td class="text-center">{{ $a->slack_days }}</td>
                            <td>@if($a->is_critical)<span class="badge text-bg-danger">Critical</span>@endif</td>
                            <td>@include('partials.badge', ['value' => $a->status]) @if($a->delay_days)<span class="badge text-bg-danger">+{{ $a->delay_days }}d</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-flag"></i> Milestones</div>
            <ul class="list-group list-group-flush">
                @forelse($milestones as $ms)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $ms->title }}<div class="small text-muted">Due {{ $ms->due_date->format('d M Y') }} · {{ $ms->responsible?->name ?? 'unassigned' }}</div></span>
                        @include('partials.badge', ['value' => $ms->state])
                    </li>
                @empty
                    <li class="list-group-item text-muted">No milestones.</li>
                @endforelse
            </ul>
        </div>
        @if(count($conflicts))
            <div class="alert alert-warning small"><strong>Resource conflicts</strong>
                <ul class="mb-0 ps-3">@foreach($conflicts as $c)<li>{{ $c['member'] }}: {{ $c['first'] }} and {{ $c['second'] }} overlap ({{ $c['days'] }}d)</li>@endforeach</ul></div>
        @endif
    </div>
</div>
@endsection