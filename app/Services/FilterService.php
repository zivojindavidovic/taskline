<?php

namespace App\Services;

use App\Models\UserProjectFilter;
use App\Repositories\FilterRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class FilterService
{
    public function __construct(private FilterRepository $repository) {}

    public function get(int $userId, int $projectId): array
    {
        $filter = $this->repository->findForUserProject($userId, $projectId);

        return [
            'sprint_ids'     => $filter?->sprint_ids     ?? [],
            'assignee_ids'   => $filter?->assignee_ids   ?? [],
            'priorities'     => $filter?->priorities     ?? [],
            'status_ids'     => $filter?->status_ids     ?? [],
            'statuses'       => $filter?->statuses       ?? [],
            'hide_completed' => $filter?->hide_completed ?? false,
            'unassigned'     => $filter?->unassigned     ?? false,
        ];
    }

    public function save(int $userId, int $projectId, array $filters): UserProjectFilter
    {
        return $this->repository->upsert($userId, $projectId, [
            'sprint_ids'     => $filters['sprint_ids']     ?? [],
            'assignee_ids'   => $filters['assignee_ids']   ?? [],
            'priorities'     => $filters['priorities']     ?? [],
            'status_ids'     => $filters['status_ids']     ?? [],
            'statuses'       => $filters['statuses']       ?? [],
            'hide_completed' => $filters['hide_completed'] ?? false,
            'unassigned'     => $filters['unassigned']     ?? false,
        ]);
    }

    public function applyToQuery(Builder|Relation $query, array $filters): Builder|Relation
    {
        if (!empty($filters['sprint_ids'])) {
            $query->whereIn('sprint_id', $filters['sprint_ids']);
        }

        if (!empty($filters['assignee_ids'])) {
            $query->whereIn('assignee_id', $filters['assignee_ids']);
        }

        if (!empty($filters['priorities'])) {
            $query->whereIn('priority', $filters['priorities']);
        }

        if (!empty($filters['status_ids'])) {
            $query->whereIn('board_column_id', $filters['status_ids']);
        }

        return $query;
    }
}
