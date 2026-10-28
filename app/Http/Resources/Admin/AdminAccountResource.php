<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * One admin account, ADM-25 – ADM-27. `is_you` lets the page keep the delete button off the logged-in admin.
 *
 * @mixin User
 */
class AdminAccountResource extends ApiResource
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
            'is_you' => $this->id === $request->user()?->id,
            'created_at' => $this->created_at,
        ];
    }
}
