<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\ProfileRepository;

class ProfileService
{
    public function __construct(private ProfileRepository $repository) {}

    public function updateProfile(User $user, array $data): User
    {
        return $this->repository->updateProfile($user, $data);
    }

    public function updateTheme(User $user, string $theme): User
    {
        return $this->repository->updateTheme($user, $theme);
    }

    public function deleteAccount(User $user): void
    {
        $this->repository->deleteAccount($user);
    }
}
