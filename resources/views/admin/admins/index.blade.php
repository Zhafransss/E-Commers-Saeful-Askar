@extends('layouts.admin')

@section('title', 'Manajemen Admin')

{{-- ADM-25 list, ADM-26 create, ADM-27 update, ADM-28 delete. Behaviour: resources/js/components/adminAdmins.js --}}
@section('content')
    <div x-data="adminAdmins" @keydown.escape.window="closeForm(); removing = null">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1>Manajemen Admin</h1>
                <p class="mt-1 text-sm text-gray-500">Akun yang bisa masuk ke panel ini. Tambah atau hapus admin di sini, tanpa mengubah file .env.</p>
            </div>
            <button type="button" @click="openCreate()" class="rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-gold-hover">+ Tambah admin</button>
        </div>

        <p x-cloak x-show="notice" class="mt-4 flex items-start justify-between gap-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            <span x-text="notice"></span>
            <button type="button" @click="notice = null" class="font-semibold" aria-label="Tutup">✕</button>
        </p>

        <form class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4" @submit.prevent="fetchPage(1)">
            <label class="min-w-64 flex-1 text-xs font-semibold text-gray-500">Cari
                <input type="search" x-model="filters.q" @input="search()" placeholder="Nama atau email"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <button type="button" @click="reset()" class="pb-2 text-sm font-semibold text-gray-500 underline hover:text-gray-900">Reset</button>
        </form>

        <div x-cloak x-show="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <span x-text="error"></span> <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white md:block">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">Admin</th><th class="px-4 py-3">Dibuat</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr x-show="loading"><td colspan="3" class="px-4 py-8 text-center text-gray-500">Memuat…</td></tr>
                    <tr x-cloak x-show="!loading && items.length === 0"><td colspan="3" class="px-4 py-8 text-center text-gray-500">Tidak ada admin yang cocok.</td></tr>
                    <template x-for="admin in items" :key="admin.id">
                        <tr>
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-2 font-medium"><span x-text="admin.name"></span>
                                    <span x-show="admin.is_you" class="rounded-full bg-gold px-2 py-0.5 text-[11px] font-semibold text-ink">Anda</span></span>
                                <span class="block text-xs text-gray-500" x-text="admin.email"></span>
                            </td>
                            <td class="px-4 py-3 text-gray-600" x-text="day(admin.created_at)"></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="openEdit(admin)" class="rounded-lg px-3 py-1.5 text-sm font-semibold hover:bg-gold-soft">Ubah</button>
                                <button type="button" x-show="!admin.is_you" @click="askRemove(admin)" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-4 space-y-3 md:hidden">
            <p x-show="loading" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Memuat…</p>
            <p x-cloak x-show="!loading && items.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Tidak ada admin yang cocok.</p>
            <template x-for="admin in items" :key="admin.id">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="flex items-center gap-2 font-medium"><span x-text="admin.name"></span>
                        <span x-show="admin.is_you" class="rounded-full bg-gold px-2 py-0.5 text-[11px] font-semibold text-ink">Anda</span></p>
                    <p class="break-all text-xs text-gray-500" x-text="admin.email"></p>
                    <p class="mt-1 text-xs text-gray-500">Dibuat <span x-text="day(admin.created_at)"></span></p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" @click="openEdit(admin)" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold">Ubah</button>
                        <button type="button" x-show="!admin.is_you" @click="askRemove(admin)" class="flex-1 rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600">Hapus</button>
                    </div>
                </div>
            </template>
        </div>

        <x-admin-pager />

        {{-- Add / edit form --}}
        <div x-cloak x-show="editing !== null" x-transition.opacity class="fixed inset-0 z-[60] flex items-end justify-center bg-ink/50 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="closeForm()">
            <form @submit.prevent="save()" class="max-h-full w-full max-w-md overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6" autocomplete="off">
                <h2 class="text-lg font-bold" x-text="editing === 0 ? 'Tambah admin' : 'Ubah admin'"></h2>
                <p x-cloak x-show="formError" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="formError"></p>

                <div class="mt-4 space-y-4">
                    <label class="block text-xs font-semibold text-gray-500">Nama
                        <input type="text" x-model="form.name" required maxlength="100" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                        <span x-show="fieldErrors.name" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.name"></span>
                    </label>
                    <label class="block text-xs font-semibold text-gray-500">Email
                        <input type="email" x-model="form.email" required maxlength="191" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                        <span x-show="fieldErrors.email" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.email"></span>
                    </label>
                    <label class="block text-xs font-semibold text-gray-500">
                        <span x-text="editing === 0 ? 'Password' : 'Password baru (kosongkan bila tidak diganti)'"></span>
                        <input type="password" x-model="form.password" :required="editing === 0" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                        <span class="mt-1 block font-normal text-gray-400">Minimal 8 karakter, mengandung huruf dan angka.</span>
                        <span x-show="fieldErrors.password" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.password"></span>
                    </label>
                    <label class="block text-xs font-semibold text-gray-500">Ulangi password
                        <input type="password" x-model="form.password_confirmation" :required="editing === 0 || form.password !== ''" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="closeForm()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold">Batal</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-gold-hover disabled:opacity-40" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button>
                </div>
            </form>
        </div>

        {{-- Delete confirmation --}}
        <div x-cloak x-show="removing" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/50 p-4 backdrop-blur-sm" @click.self="removing = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-bold">Hapus admin?</h2>
                <p class="mt-2 text-sm text-gray-600"><span class="font-semibold" x-text="removing?.name"></span> (<span x-text="removing?.email"></span>) tidak bisa lagi masuk ke panel admin, dan sesinya yang sedang aktif langsung berakhir.</p>
                <p x-cloak x-show="removeError" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="removeError"></p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="removing = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold">Batal</button>
                    <button type="button" @click="remove()" :disabled="removeBusy" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-40" x-text="removeBusy ? 'Menghapus…' : 'Hapus'"></button>
                </div>
            </div>
        </div>
    </div>
@endsection
