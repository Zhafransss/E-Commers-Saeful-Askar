@if (config('app.api_mock'))
    <div class="bg-amber-100 px-4 py-1 text-center text-xs text-amber-900">
        Mode mock aktif (VITE_API_MOCK=true): data berasal dari contoh API Contract, bukan backend.
    </div>
@endif
