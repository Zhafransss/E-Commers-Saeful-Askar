{{-- Page buttons of an admin list. Reads `meta`, `fetchPage()` and `canNext()` from the page's Alpine component. --}}
<div x-cloak x-show="meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
    <button type="button" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)" class="rounded-lg border border-gray-300 bg-white px-4 py-2 font-semibold disabled:opacity-40">← Sebelumnya</button>
    <span class="text-gray-500">Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span></span>
    <button type="button" :disabled="!canNext()" @click="fetchPage(meta.current_page + 1)" class="rounded-lg border border-gray-300 bg-white px-4 py-2 font-semibold disabled:opacity-40">Berikutnya →</button>
</div>
