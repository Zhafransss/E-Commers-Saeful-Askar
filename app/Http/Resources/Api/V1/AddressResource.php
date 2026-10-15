<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\Address;
use Illuminate\Http\Request;

/**
 * Address object (docs/api/README.md). Region IDs are Jubelio's, as strings.
 *
 * @mixin Address
 */
class AddressResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'address_line' => $this->address_line,
            'province' => ['id' => $this->province_id, 'name' => $this->province_name],
            'city' => ['id' => $this->city_id, 'name' => $this->city_name],
            'district' => ['id' => $this->district_id, 'name' => $this->district_name],
            'subdistrict' => ['id' => $this->subdistrict_id, 'name' => $this->subdistrict_name],
            'post_code' => $this->post_code,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
