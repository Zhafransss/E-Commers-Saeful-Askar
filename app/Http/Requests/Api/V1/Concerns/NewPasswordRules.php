<?php

namespace App\Http\Requests\Api\V1\Concerns;

/**
 * New-password rule shared by AUTH-02, AUTH-07, and AUTH-09: at least 8
 * characters with letters and digits, confirmed.
 */
trait NewPasswordRules
{
    private const PASSWORD_MESSAGE = 'Password minimal 8 karakter dan mengandung huruf serta angka.';

    /**
     * @return list<string>
     */
    protected function newPasswordRules(): array
    {
        return ['required', 'string', 'min:8', 'max:255', 'regex:/[A-Za-z]/', 'regex:/\d/', 'confirmed'];
    }

    /**
     * @return array<string, string>
     */
    protected function newPasswordMessages(): array
    {
        return [
            'password.min' => self::PASSWORD_MESSAGE,
            'password.regex' => self::PASSWORD_MESSAGE,
        ];
    }
}
