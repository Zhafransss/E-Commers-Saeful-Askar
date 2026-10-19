{{-- Banner for errors not tied to one field (`message`) and for the success text (`success`). --}}
<div x-cloak x-show="message" x-text="message" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>
<div x-cloak x-show="success" x-text="success" role="status" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"></div>
