@extends('layouts.app')

@section('title', 'Atur Ulang Password')
@section('robots', 'noindex, follow')
@section('body_class', 'bg-neutral-950 text-white')

{{-- AUTH-09 { token, email, password, password_confirmation }; token and email come from the AUTH-08 email link. --}}
@section('content')
    <x-auth-shell title="Atur ulang password" subtitle="Buat password baru untuk akun {{ $email }}.">
        <form method="post" novalidate class="space-y-5" x-data="resetPasswordForm({ token: @js($token), email: @js($email) })" @submit.prevent="submit()">
            <x-form-alert />
            <x-field-error name="token" />
            <x-field-error name="email" />

            <x-password-input name="password" label="Password baru" autocomplete="new-password" x-model="form.password">
                <span class="mt-1 block text-xs text-gray-500">Minimal 8 karakter, ada huruf dan angka.</span>
                <x-field-error name="password" />
            </x-password-input>
            <x-password-input name="password_confirmation" label="Ulangi password baru" autocomplete="new-password" x-model="form.password_confirmation">
                <x-field-error name="password_confirmation" />
            </x-password-input>

            <button type="submit" :disabled="busy || success" class="w-full rounded-full bg-brand py-3.5 font-semibold text-ink transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-60" x-text="busy ? 'Menyimpan…' : 'Simpan password'">Simpan password</button>

            <a x-cloak x-show="success" href="{{ route('login') }}" class="block w-full rounded-full border-2 border-ink py-3 text-center font-semibold text-ink transition hover:bg-ink hover:text-brand">Masuk sekarang</a>
        </form>
    </x-auth-shell>
@endsection
