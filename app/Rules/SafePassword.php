<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafePassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || str_contains($value, "\0")) {
            $fail('Le mot de passe contient des caractères non autorisés.');
        } elseif (strlen($value) > 72) {
            $fail('Le mot de passe est trop long (72 octets maximum ; les accents peuvent compter plusieurs octets).');
        }
    }
}
