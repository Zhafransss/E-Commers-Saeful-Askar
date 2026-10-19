<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * User object (docs/api/auth.md). Never carries password, remember_token, or
 * jubelio_contact_id: the fields are listed explicitly.
 *
 * @mixin User
 */
class UserResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'email_verified_at' => $this->email_verified_at,
            'has_default_address' => $this->defaultAddress()->exists(),
            'created_at' => $this->created_at,
        ];
    }
}
