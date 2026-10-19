<?php

namespace App\Http\Requests\Api\V1;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ADDR-02 and ADDR-04 (same body). Region names are never taken from the
 * client: AddressService fills them from Jubelio's lists after checking the
 * province → city → district → subdistrict chain.
 */
class StoreAddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::clean($this->phone),
            'post_code' => is_string($this->post_code) ? trim($this->post_code) : $this->post_code,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', PhoneNumber::RULE],
            'address_line' => ['required', 'string', 'min:10', 'max:255'],
            'province_id' => ['required', 'string', 'max:2'],
            'city_id' => ['required', 'string', 'max:4'],
            'district_id' => ['required', 'string', 'max:6'],
            'subdistrict_id' => ['required', 'string', 'max:10'],
            'post_code' => ['required', 'string', 'digits:5'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => PhoneNumber::MESSAGE,
            'province_id.required' => 'Provinsi wajib dipilih.',
            'city_id.required' => 'Kota/kabupaten wajib dipilih.',
            'district_id.required' => 'Kecamatan wajib dipilih.',
            'subdistrict_id.required' => 'Kelurahan/desa wajib dipilih.',
        ];
    }
}
