<?php

namespace App\Support;

use App\Models\User;

/**
 * Indonesian mobile numbers (AUTH-02, AUTH-06, ADDR-02): 10–15 digits starting
 * with 08 or 628, stored as 08…. Spaces, dashes, dots, and a leading + are
 * accepted on input, since customers often type "+62 812-3456-7890".
 */
class PhoneNumber
{
    /** Validation rule for a cleaned number (see clean()). */
    public const RULE = 'regex:/^(08\d{8,13}|628\d{7,12})$/';

    public const MESSAGE = 'Nomor HP tidak valid.';

    public const TAKEN_MESSAGE = 'Nomor HP sudah terdaftar di akun lain.';

    /**
     * Strips separators so validation sees digits only; anything else is left
     * for the rule to reject.
     */
    public static function clean(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/[\s\-.()]+|^\+/', '', trim($value)) : $value;
    }

    /**
     * 6281234567890 → 081234567890. Call after validation.
     */
    public static function normalize(string $cleaned): string
    {
        return str_starts_with($cleaned, '62') ? '0'.substr($cleaned, 2) : $cleaned;
    }

    /**
     * Validation rule: no other account has this number. 628… and 08… are the same number, so the comparison
     * is made on the stored form. Put it after RULE with `bail`, so it only runs on a number that is valid.
     *
     * @param  int|null  $exceptUserId  The account being edited, which may keep its own number
     */
    public static function uniqueRule(?int $exceptUserId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($exceptUserId): void {
            if (self::isTaken((string) $value, $exceptUserId)) {
                $fail(self::TAKEN_MESSAGE);
            }
        };
    }

    public static function isTaken(string $number, ?int $exceptUserId = null): bool
    {
        return User::query()
            ->where('phone', self::normalize(self::clean($number)))
            ->when($exceptUserId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();
    }
}
