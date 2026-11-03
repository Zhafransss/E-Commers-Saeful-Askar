<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingVoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-31 (create) and ADM-32 (update) send the whole voucher. Times are the shop's time (Asia/Jakarta), as the
 * admin types them.
 */
class SaveShippingVoucherRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // A free-shipping voucher has no amount; an empty field means "no limit".
        $this->merge([
            'amount' => $this->input('type') === ShippingVoucherType::FreeShipping->value ? null : $this->input('amount'),
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
            'quota' => $this->filled('quota') ? $this->input('quota') : null,
            'per_user_limit' => $this->filled('per_user_limit') ? $this->input('per_user_limit') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'type' => ['required', Rule::enum(ShippingVoucherType::class)],
            'amount' => ['nullable', Rule::requiredIf($this->input('type') === ShippingVoucherType::ShippingDiscount->value), 'integer', 'min:1', 'max:10000000'],
            'min_subtotal' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama voucher wajib diisi.',
            'name.min' => 'Nama voucher minimal 3 karakter.',
            'type.required' => 'Pilih jenis voucher.',
            'amount.required' => 'Isi besar potongan ongkir.',
            'amount.min' => 'Potongan ongkir minimal Rp1.',
            'min_subtotal.required' => 'Isi minimal belanja (0 bila tanpa minimal).',
            'starts_at.required' => 'Isi waktu mulai berlaku.',
            'ends_at.after' => 'Waktu berakhir harus setelah waktu mulai.',
            'quota.min' => 'Kuota minimal 1, atau kosongkan bila tanpa batas.',
            'per_user_limit.min' => 'Batas per pembeli minimal 1, atau kosongkan bila tanpa batas.',
        ];
    }
}
