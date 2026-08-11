<?php

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

final class OperationalPassword
{
    public static function rule(): Password
    {
        return Password::min(12)->mixedCase()->letters()->numbers()->symbols();
    }
}
