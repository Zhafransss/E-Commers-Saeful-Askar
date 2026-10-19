@extends('layouts.app')

@section('title', 'Masuk')
@section('robots', 'noindex, follow')
@section('body_class', 'bg-neutral-950 text-white')

{{-- AUTH-03 { email, password, remember }; the response is the User (AUTH-05 shape). --}}
@section('content')
    <x-auth-shell title="Masuk ke akun" subtitle="Selamat datang kembali! Masuk untuk checkout dan melacak pesanan.">
        <form method="post" novalidate class="space-y-5" x-data="loginForm({ intended: @js($intended) })" @submit.prevent="submit()">
            <x-form-alert />

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-ink">Email</span>
                <input type="email" name="email" x-model="form.email" autocomplete="email" placeholder="nama@email.com" autofocus
                       class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-ink placeholder:text-gray-400 focus:border-ink focus:outline-none focus:ring-2 focus:ring-brand/40">
                <x-field-error name="email" />
            </label>

            <x-password-input name="password" label="Password" x-model="form.password">
                <x-field-error name="password" />
            </x-password-input>

            <div class="flex items-center justify-between gap-4 text-sm">
                <label class="flex cursor-pointer items-center gap-2 text-gray-700">
                    <input type="checkbox" name="remember" x-model="form.remember" class="h-4 w-4 rounded accent-ink">
                    Ingat saya
                </label>
                <a href="{{ route('password.request') }}" class="font-semibold text-ink underline decoration-brand decoration-2 underline-offset-4 hover:text-gray-600">Lupa password?</a>
            </div>

            <button type="submit" :disabled="busy" class="w-full rounded-full bg-brand py-3.5 font-semibold text-ink transition hover:bg-brand-hover disabled:cursor-wait disabled:opacity-60" x-text="busy ? 'Memproses…' : 'Masuk'">Masuk</button>
        </form>

        <div class="my-8 flex items-center gap-4 text-xs uppercase tracking-widest text-gray-400">
            <span class="h-px flex-1 bg-gray-200"></span> Belum punya akun? <span class="h-px flex-1 bg-gray-200"></span>
        </div>

        <a href="{{ route('register', request()->only('next')) }}" class="block w-full rounded-full border-2 border-ink py-3 text-center font-semibold text-ink transition hover:bg-ink hover:text-brand">Daftar sekarang</a>

        <p class="mt-8 text-center text-xs text-gray-500">
            Butuh bantuan? <a href="{{ route('contact') }}" class="font-semibold text-ink hover:underline">Hubungi kami</a>
        </p>
    </x-auth-shell>
@endsection
