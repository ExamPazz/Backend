<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$%^&+=!_]).{8,}$/', $value))
        {
            $fail('The :attribute must be at least 8 characters long and contain at least 1 uppercase letter, 1 number, and 1 symbol.');
        }
    }
}
