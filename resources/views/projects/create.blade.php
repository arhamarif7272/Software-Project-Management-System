@extends('layouts.master')
@section('title', 'New project')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <h3 class="mb-3">New project: charter</h3>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('projects.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Project name *</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Project objectives *</label>
                            <textarea name="objectives" rows="3" class="form-control" required>{{ old('objectives') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Scope boundaries (in scope / out of scope) *</label>
                            <textarea name="scope_boundaries" rows="3" class="form-control" required>{{ old('scope_boundaries') }}</textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Approved budget (PKR) *</label>
                                <input type="number" step="0.01" min="1" name="approved_budget" value="{{ old('approved_budget') }}" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start date *</label>
                                <input type="date" name="start_date" value="{{ old('start_date') }}" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Deadline *</label>
                                <input type="date" name="approved_deadline" value="{{ old('approved_deadline') }}" class="form-control" required>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary">Create charter</button>
                            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection