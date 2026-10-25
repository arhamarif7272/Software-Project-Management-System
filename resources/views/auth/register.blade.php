@extends('layouts.guest')
@section('title', 'Register')

@section('content')
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Full name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
            <div class="form-text">At least 8 characters, with letters and numbers.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Create account</button>
    </form>
    <div class="text-center mt-3 small">Already registered? <a href="{{ route('login') }}">Sign in</a></div>
@endsection