<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Software PMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --brand: #124E66; }
        body { min-height: 100vh; background: radial-gradient(circle at 20% 20%, #212A31 0%, #124E66 100%); display: flex; align-items: center; justify-content: center; }
        .auth-card { width: 100%; max-width: 420px; border: 0; border-radius: 14px; box-shadow: 0 18px 48px rgba(0,0,0,.35); }
        .auth-brand i { color: var(--brand); font-size: 2.6rem; }
        .btn-primary { --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand);
                       --bs-btn-hover-bg: #0E3B4D; --bs-btn-hover-border-color: #0E3B4D; }
        a { color: var(--brand); }
        a:hover { color: #0E3B4D; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card auth-card mx-auto">
        <div class="card-body p-4">
            <div class="text-center mb-3 auth-brand">
                <i class="bi bi-clipboard-data"></i>
                <h4 class="mb-0 mt-1">Software PMS</h4>
                <div class="text-muted small">Software Project Management System</div>
            </div>

            @if(session('success'))
                <div class="alert alert-success py-2">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul></div>
            @endif

            @yield('content')
        </div>
    </div>
</div>
</body>
</html>