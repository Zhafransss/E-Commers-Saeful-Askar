<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NewPasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AUTH-07. Whether current_password matches is checked by the controller,
 * which answers INVALID_CURRENT_PASSWORD rather than a field error.
 */
class UpdatePasswordRequest extends FormRequest
{
    use NewPasswordRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [...$this->newPasswordRules(), 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ] + $this->newPasswordMessages();
    }
}
