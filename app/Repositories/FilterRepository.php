<?php

namespace App\Repositories;

use App\Models\UserProjectFilter;

class FilterRepository
{
    public function findForUserProject(int $userId, int $projectId): ?UserProjectFilter
    {
        return UserProjectFilter::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->first();
    }

    public function upsert(int $userId, int $projectId, array $data): UserProjectFilter
    {
        return UserProjectFilter::updateOrCreate(
            ['user_id' => $userId, 'project_id' => $projectId],
            $data
        );
    }

    public function delete(int $userId, int $projectId): void
    {
        UserProjectFilter::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->delete();
    }
}
