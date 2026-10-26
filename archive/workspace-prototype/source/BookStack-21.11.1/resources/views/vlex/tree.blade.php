@foreach(($tree[$treeParent ?? ''] ?? []) as $branch)
    <div class="tree-branch">
        <a class="tree-link {{ $current && $current->id === $branch->id ? 'selected' : '' }}" href="{{ url('/vlex?node='.$branch->id) }}"><span class="tree-dot">⊕</span>{{ $branch->name }}</a>
        @if(isset($tree[$branch->id]))
            <div class="tree-children">@include('vlex.tree', ['treeParent' => $branch->id])</div>
        @endif
    </div>
@endforeach
