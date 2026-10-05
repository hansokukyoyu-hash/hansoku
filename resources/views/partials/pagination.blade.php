@if ($paginator->hasPages())
    <nav class="row small" style="margin-top:12px;align-items:center">
        @if ($paginator->onFirstPage())<span class="muted">← 前へ</span>@else<a href="{{ $paginator->previousPageUrl() }}">← 前へ</a>@endif
        <span class="muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">次へ →</a>@else<span class="muted">次へ →</span>@endif
    </nav>
@endif
