<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\V1\Concerns\NewPasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ADM-26. The email is lower-cased before the unique check, and must be free among customers too.
 */
class StoreAdminRequest extends FormRequest
{
    use NewPasswordRules;

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => $this->newPasswordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.unique' => 'Email sudah terdaftar.'] + $this->newPasswordMessages();
    }
}
