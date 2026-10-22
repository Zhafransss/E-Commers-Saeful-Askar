<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NewPasswordRules;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AUTH-02. Email is lower-cased before the unique check, so "Budi@Example.com"
 * and "budi@example.com" are the same account.
 */
class RegisterRequest extends FormRequest
{
    use NewPasswordRules;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            'phone' => PhoneNumber::clean($this->phone),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['bail', 'required', 'string', PhoneNumber::RULE, PhoneNumber::uniqueRule()],
            'password' => $this->newPasswordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'phone.regex' => PhoneNumber::MESSAGE,
        ] + $this->newPasswordMessages();
    }
}
