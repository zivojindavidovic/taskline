<?php

namespace App\Repositories;

use App\Models\User;

class ProfileRepository
{
    public function updateProfile(User $user, array $data): User
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }

    public function updateTheme(User $user, string $theme): User
    {
        $user->update(['theme' => $theme]);

        return $user;
    }

    public function deleteAccount(User $user): void
    {
        $user->delete();
    }
}
