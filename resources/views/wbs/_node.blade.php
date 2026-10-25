<li>
    <div class="tree-node d-flex justify-content-between align-items-center gap-2">
        <div>
            <span class="badge text-bg-dark">{{ $node->wbs_code }}</span>
            <strong>{{ $node->name }}</strong>
            <span class="badge text-bg-light border text-muted">{{ ucwords(str_replace('_', ' ', $node->level_type)) }}</span>
            @if($node->requirement)<span class="badge text-bg-info">{{ $node->requirement->code }}</span>@endif
        </div>
        <form method="POST" action="{{ route('wbs.destroy', $node) }}" data-confirm="Delete {{ $node->wbs_code }} and everything below it?" class="m-0">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
        </form>
    </div>
    @if($node->childrenRecursive->isNotEmpty())
        <ul class="tree">
            @foreach($node->childrenRecursive as $child)
                @include('wbs._node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
