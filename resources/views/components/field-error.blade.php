@props(['name'])

{{-- Per-field message from a 422 (the enclosing Alpine component is built by authForm in authForms.js). --}}
<span x-cloak x-show="fieldError('{{ $name }}')" x-text="fieldError('{{ $name }}')" role="alert" class="mt-1 block text-xs font-medium text-red-600"></span>
