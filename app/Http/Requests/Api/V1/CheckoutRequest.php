<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ORD-01 (docs/api/checkout-orders.md). The items come from the customer's cart, so only what the
 * customer chose is sent: address, rate (from SHIP-01), a note, and the total they saw. The
 * `Idempotency-Key` header, a fresh UUID per click on "Bayar", is validated as a field of its own.
 */
class CheckoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'address_id' => ['required', 'integer'],
            'rate_id' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
            'expected_grand_total' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Header Idempotency-Key wajib diisi.',
            'idempotency_key.uuid' => 'Header Idempotency-Key harus berupa UUID.',
            'address_id.required' => 'Alamat pengiriman wajib dipilih.',
            'rate_id.required' => 'Kurir pengiriman wajib dipilih.',
            'note.max' => 'Catatan maksimal 255 karakter.',
            'expected_grand_total.required' => 'Total pesanan wajib dikirim.',
        ];
    }
}
