@props(['name', 'label', 'autocomplete' => 'current-password', 'placeholder' => '••••••••'])

{{-- Password field with a show/hide toggle. Extra attributes go to the <input>. --}}
<label class="block" x-data="{ visible: false }">
    <span class="mb-1.5 block text-sm font-medium text-ink">{{ $label }}</span>
    <span class="relative block">
        <input :type="visible ? 'text' : 'password'" type="password" name="{{ $name }}" autocomplete="{{ $autocomplete }}" placeholder="{{ $placeholder }}"
               {{ $attributes->class('w-full rounded-xl border border-gray-300 bg-white py-3 pl-4 pr-12 text-sm text-ink placeholder:text-gray-400 focus:border-ink focus:outline-none focus:ring-2 focus:ring-brand/40') }}>
        <button type="button" @click="visible = ! visible" :aria-label="visible ? 'Sembunyikan password' : 'Tampilkan password'"
                class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-ink">
            <svg x-show="! visible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
            <svg x-cloak x-show="visible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.7 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6" /></svg>
        </button>
    </span>
    {{ $slot }}
</label>
