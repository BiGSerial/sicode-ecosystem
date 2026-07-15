<?php

declare(strict_types=1);

namespace App\LocalAuthentication;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

final class ResolveLocalSessionUser
{
    public function __invoke(Session $session): ?User
    {
        $userId = $session->get(LocalSession::USER_ID_KEY);

        if (! is_string($userId) || ! Str::isUuid($userId)) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->whereKey($userId)->first();

        return $user;
    }
}
