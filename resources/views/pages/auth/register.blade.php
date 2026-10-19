@extends('layouts.app')

@section('title', 'Daftar')
@section('robots', 'noindex, follow')
@section('body_class', 'bg-neutral-950 text-white')

{{--
    UI only for now. Live: POST AUTH-02 { name, email, phone, password, password_confirmation }.
    The checklist mirrors the AUTH-02 password rule: min 8 characters with letters and numbers.
--}}
@section('content')
    <x-auth-shell title="Buat akun" subtitle="Daftar untuk checkout lebih cepat, simpan alamat, dan lacak pesanan.">
        <form method="post" novalidate @submit.prevent class="space-y-5"
              x-data="{ password: '', confirmation: '' }">
            @foreach ([
                ['name', 'Nama lengkap', 'text', 'Nama sesuai identitas', 'name'],
                ['email', 'Email', 'email', 'nama@email.com', 'email'],
                ['phone', 'Nomor HP', 'tel', '08xxxxxxxxxx', 'tel'],
            ] as [$field, $label, $type, $placeholder, $autocomplete])
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-ink">{{ $label }}</span>
                    <input type="{{ $type }}" name="{{ $field }}" autocomplete="{{ $autocomplete }}" placeholder="{{ $placeholder }}" @if ($loop->first) autofocus @endif
                           class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-ink placeholder:text-gray-400 focus:border-ink focus:outline-none focus:ring-2 focus:ring-brand/40">
                    @if ($field === 'phone')
                        <span class="mt-1 block text-xs text-gray-500">Dipakai kurir untuk menghubungi Anda saat pengiriman.</span>
                    @endif
                </label>
            @endforeach

            <div class="grid gap-5 sm:grid-cols-2">
                <x-password-input name="password" label="Password" autocomplete="new-password" x-model="password" />
                <x-password-input name="password_confirmation" label="Ulangi password" autocomplete="new-password" x-model="confirmation" />
            </div>

            <ul class="grid gap-1.5 text-xs sm:grid-cols-2" aria-label="Syarat password">
                @foreach ([
                    ['password.length >= 8', 'Minimal 8 karakter'],
                    ['/[A-Za-z]/.test(password) && /\d/.test(password)', 'Ada huruf dan angka'],
                    ['confirmation.length > 0 && confirmation === password', 'Password sama'],
                ] as [$rule, $text])
                    <li class="flex items-center gap-2 transition" :class="{{ $rule }} ? 'text-green-700' : 'text-gray-500'">
                        <span class="grid h-4 w-4 place-items-center rounded-full border transition"
                              :class="{{ $rule }} ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300'">
                            <svg x-show="{{ $rule }}" class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                        </span>
                        {{ $text }}
                    </li>
                @endforeach
            </ul>

            <button type="submit" class="w-full rounded-full bg-brand py-3.5 font-semibold text-ink transition hover:bg-brand-hover">Daftar</button>

            <p class="text-center text-xs leading-relaxed text-gray-500">
                Dengan mendaftar, Anda setuju dihubungi toko terkait pesanan Anda melalui email, telepon, atau WhatsApp.
            </p>
        </form>

        <p class="mt-8 border-t border-gray-200 pt-6 text-center text-sm text-gray-600">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-ink underline decoration-brand decoration-2 underline-offset-4 hover:text-gray-600">Masuk</a>
        </p>
    </x-auth-shell>
@endsection
