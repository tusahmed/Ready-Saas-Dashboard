@if($paginator->hasPages())
<nav class="pagination" aria-label="{{ __('app.pagination') }}">
    <span class="muted">{{ __('app.pagination_summary', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</span>
    <div class="page-actions">
        @if($paginator->onFirstPage())<span class="btn btn-secondary btn-sm" aria-disabled="true">{{ __('app.previous') }}</span>@else<a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('app.previous') }}</a>@endif
        <span class="muted">{{ __('app.page_of', ['page' => $paginator->currentPage(), 'total' => $paginator->lastPage()]) }}</span>
        @if($paginator->hasMorePages())<a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('app.next') }}</a>@else<span class="btn btn-secondary btn-sm" aria-disabled="true">{{ __('app.next') }}</span>@endif
    </div>
</nav>
@endif
