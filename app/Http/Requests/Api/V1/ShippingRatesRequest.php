<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SHIP-01 body. The package comes from the cart, so only the address, the chosen cart lines (`item_ids`, the whole cart when
 * left out), and an optional filter are sent.
 */
class ShippingRatesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer'],
            'item_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['integer'],
            'service_types' => ['sometimes', 'array'],
            'service_types.*' => ['string', Rule::in(['instant', 'same_day', 'regular', 'cargo'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.required' => 'Alamat pengiriman wajib dipilih.',
            'item_ids.min' => 'Pilih minimal satu produk untuk di-checkout.',
            'service_types.*.in' => 'Jenis layanan tidak dikenal.',
        ];
    }
}
