<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-27. Every field is optional; a password is only changed when one is sent.
 */
class UpdateAdminRequest extends FormRequest
{
    private const PASSWORD_MESSAGE = 'Password minimal 8 karakter dan mengandung huruf serta angka.';

    protected function prepareForValidation(): void
    {
        // Only fields that were sent are touched: a missing key must stay missing for `sometimes` to skip it.
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }

        // An empty password box means "keep the current one".
        if ($this->password === '') {
            $this->merge(['password' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:191', Rule::unique('users', 'email')->ignore((int) $this->route('id'))],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'regex:/[A-Za-z]/', 'regex:/\d/', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'password.min' => self::PASSWORD_MESSAGE,
            'password.regex' => self::PASSWORD_MESSAGE,
        ];
    }
}
