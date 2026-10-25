@extends('layouts.guest')
@section('title', 'Login')

@section('content')
    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <button class="btn btn-primary w-100">Sign in</button>
    </form>
    <div class="text-center mt-3 small">No account yet? <a href="{{ route('register') }}">Register</a></div>
@endsection