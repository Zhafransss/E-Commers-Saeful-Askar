@props(['title', 'subtitle' => null])

{{-- Shared frame for the login and register pages: brand panel + form card. --}}
<section class="bg-neutral-950 py-10 md:py-16">
    <div class="mx-auto grid max-w-6xl overflow-hidden rounded-none md:rounded-3xl md:border md:border-neutral-800 lg:grid-cols-2">
        {{-- Brand panel --}}
        <div class="relative hidden overflow-hidden lg:block">
            <img src="{{ asset('images/banner.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/70 to-neutral-950/30"></div>
            <div class="relative flex h-full flex-col justify-between p-10 text-white">
                <a href="{{ route('home') }}" class="font-logo text-2xl font-bold">Saeful <span class="text-brand">Askar</span></a>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Toko Sepeda Listrik Bekasi</p>
                    <h2 class="mt-3 text-3xl font-bold leading-tight">Belanja sepeda listrik &amp; sparepart, langsung dari gudang.</h2>
                    <ul class="mt-8 space-y-3 text-sm text-neutral-300">
                        @foreach (['Harga dan stok sesuai gudang', 'Pembayaran aman via DANA', 'Lacak pesanan dan nomor resi', 'Service di rumah atau di toko'] as $point)
                            <li class="flex items-center gap-3">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand text-ink">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                                </span>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- Form card --}}
        <div class="bg-white px-5 py-10 sm:px-12 md:py-14">
            <div class="mx-auto w-full max-w-md">
                <h1 class="text-3xl font-bold text-ink">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-2 text-gray-600">{{ $subtitle }}</p>
                @endif
                <div class="mt-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</section>
