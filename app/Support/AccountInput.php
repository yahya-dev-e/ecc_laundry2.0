<?php

namespace App\Support;

use Illuminate\Http\Request;

class AccountInput
{
    public static function email(mixed $value): string
    {
        return is_string($value) ? strtolower(trim($value)) : '';
    }

    public static function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => self::email($request->input('email'))]);
        }
    }
}
