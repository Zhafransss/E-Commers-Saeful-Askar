<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CAT-02 query.
 */
class ListProductsRequest extends FormRequest
{
    public const SORTS = ['latest', 'price_asc', 'price_desc', 'name_asc'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:170'],
            'in_stock' => ['sometimes', 'boolean'],
            'price_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // gte fails against an absent field, so it only applies when price_min is sent.
            'price_max' => ['sometimes', 'nullable', 'integer', 'min:0', ...($this->filled('price_min') ? ['gte:price_min'] : [])],
            'sort' => ['sometimes', 'in:'.implode(',', self::SORTS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.in' => 'Pilihan urutan tidak dikenal.',
        ];
    }

    /**
     * Field names as customers read them in error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => 'kata kunci',
            'category' => 'kategori',
            'in_stock' => 'filter stok tersedia',
            'price_min' => 'harga minimum',
            'price_max' => 'harga maksimum',
            'sort' => 'urutan',
            'page' => 'halaman',
            'per_page' => 'jumlah per halaman',
        ];
    }
}
