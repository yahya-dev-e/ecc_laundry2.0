@if($paginator->hasPages())
<nav aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 mt-5">
    @if($paginator->onFirstPage())
        <span aria-disabled="true" class="px-4 py-3 text-slate-500">Précédent</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-primary">Précédent</a>
    @endif
    <span class="text-sm text-slate-600">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-primary">Suivant</a>
    @else
        <span aria-disabled="true" class="px-4 py-3 text-slate-500">Suivant</span>
    @endif
</nav>
@endif
