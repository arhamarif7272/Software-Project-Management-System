@php
    $key = strtolower((string) ($value ?? ''));
    $tone = match (true) {
        in_array($key, ['approved', 'implemented', 'completed', 'achieved', 'pass', 'closed', 'verified', 'done', 'on_track', 'low', 'resolved']) => 'success',
        in_array($key, ['in_progress', 'mitigating', 'high', 'warning', 'at_risk', 'deferred', 'on_hold', 'executing', 'monitoring']) => 'warning',
        in_array($key, ['delayed', 'rejected', 'fail', 'critical', 'missed', 'extreme', 'open', 'reopened', 'occurred']) => 'danger',
        in_array($key, ['pending', 'proposed', 'planned', 'not_started', 'identified', 'info', 'initiation', 'planning', 'closing']) => 'secondary',
        default => 'info',
    };
@endphp
<span class="badge badge-{{ $tone }}">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>