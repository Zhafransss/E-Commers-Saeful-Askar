@extends('layouts.app')

@section('title', 'Lupa Password')
@section('robots', 'noindex, follow')
@section('body_class', 'bg-neutral-950 text-white')

{{-- AUTH-08 { email }; the answer is the same whether or not the email is registered. --}}
@section('content')
    <x-auth-shell title="Lupa password" subtitle="Masukkan email akun Anda. Kami kirim tautan untuk mengatur ulang password.">
        <form method="post" novalidate class="space-y-5" x-data="forgotPasswordForm()" @submit.prevent="submit()">
            <x-form-alert />

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-ink">Email</span>
                <input type="email" name="email" x-model="form.email" autocomplete="email" placeholder="nama@email.com" autofocus
                       class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-ink placeholder:text-gray-400 focus:border-ink focus:outline-none focus:ring-2 focus:ring-brand/40">
                <x-field-error name="email" />
            </label>

            <button type="submit" :disabled="busy" class="w-full rounded-full bg-brand py-3.5 font-semibold text-ink transition hover:bg-brand-hover disabled:cursor-wait disabled:opacity-60" x-text="busy ? 'Mengirim…' : 'Kirim tautan reset'">Kirim tautan reset</button>
        </form>

        <p class="mt-8 border-t border-gray-200 pt-6 text-center text-sm text-gray-600">
            Ingat password Anda?
            <a href="{{ route('login') }}" class="font-semibold text-ink underline decoration-brand decoration-2 underline-offset-4 hover:text-gray-600">Masuk</a>
        </p>
    </x-auth-shell>
@endsection
