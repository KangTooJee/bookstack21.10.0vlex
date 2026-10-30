<div class="entity-list">
    @forelse($targets as $entity)
        @component('entities.list-item-basic', ['entity' => $entity])
            <p class="text-muted break-text">{{ app(\BookStack\ViewComponents\HierarchyFolder::class)->path($entity) }}</p>
        @endcomponent
    @empty
        <p class="text-muted p-m">Không tìm thấy book đích phù hợp hoặc bạn chưa có quyền thêm file vào vị trí này.</p>
    @endforelse
</div>
