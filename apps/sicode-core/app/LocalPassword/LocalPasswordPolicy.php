<?php

declare(strict_types=1);

namespace App\LocalPassword;

use Illuminate\Validation\Rules\Password;

final class LocalPasswordPolicy
{
    public const int MIN_LENGTH = 12;

    /**
     * @return array<int, Password>
     */
    public function rules(): array
    {
        return [
            Password::min(self::MIN_LENGTH),
        ];
    }
}
