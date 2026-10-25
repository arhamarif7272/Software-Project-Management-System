@extends('layouts.master')
@section('title', 'Projects')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Projects</h3>
        <a href="{{ route('projects.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New project</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Project</th><th>Status</th><th class="text-end">Current budget</th>
                    <th>Deadline</th><th class="text-center">Activities</th>
                    <th class="text-center">Requirements</th><th class="text-center">Defects</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($projects as $p)
                    <tr>
                        <td>
                            <a href="{{ route('dashboard', $p) }}" class="fw-semibold text-decoration-none">{{ $p->name }}</a>
                            @if($p->charter_approved)<i class="bi bi-patch-check-fill text-success" title="Charter approved"></i>@endif
                        </td>
                        <td>@include('partials.badge', ['value' => $p->status])</td>
                        <td class="text-end money">PKR {{ number_format($p->current_budget) }}</td>
                        <td>{{ $p->current_deadline->format('d M Y') }}</td>
                        <td class="text-center">{{ $p->activities_count }}</td>
                        <td class="text-center">{{ $p->requirements_count }}</td>
                        <td class="text-center">{{ $p->defects_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('projects.show', $p) }}" class="btn btn-sm btn-outline-primary">Charter</a>
                            <a href="{{ route('dashboard', $p) }}" class="btn btn-sm btn-primary">Dashboard</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        No projects yet. Create one, or run <code>php artisan migrate:fresh --seed</code> to load the healthcare case study.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection