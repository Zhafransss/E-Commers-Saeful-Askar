{{-- Page buttons of an admin list. Reads `meta`, `fetchPage()` and `canNext()` from the page's Alpine component. --}}
<div x-cloak x-show="meta.last_page > 1" class="mt-4 flex items-center justify-between gap-2 text-sm">
    <button type="button" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)" class="shrink-0 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold disabled:opacity-40 sm:px-4"><span aria-hidden="true">←</span><span class="sr-only min-[400px]:not-sr-only"> Sebelumnya</span></button>
    <span class="min-w-0 text-center text-gray-500"><span class="hidden min-[400px]:inline">Halaman </span><span x-text="meta.current_page"></span><span class="min-[400px]:hidden"> / </span><span class="hidden min-[400px]:inline"> dari </span><span x-text="meta.last_page"></span></span>
    <button type="button" :disabled="!canNext()" @click="fetchPage(meta.current_page + 1)" class="shrink-0 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold disabled:opacity-40 sm:px-4"><span class="sr-only min-[400px]:not-sr-only">Berikutnya </span><span aria-hidden="true">→</span></button>
</div>
