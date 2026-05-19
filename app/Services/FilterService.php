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
            'view_mode'      => $filter?->view_mode      ?? 'active',
            'view_sprint_id' => $filter?->view_sprint_id,
        ];
    }

    public function save(int $userId, int $projectId, array $filters): UserProjectFilter
    {
        $existing = $this->repository->findForUserProject($userId, $projectId);

        $data = [
            'sprint_ids'     => $filters['sprint_ids']     ?? $existing?->sprint_ids     ?? [],
            'assignee_ids'   => $filters['assignee_ids']   ?? $existing?->assignee_ids   ?? [],
            'priorities'     => $filters['priorities']     ?? $existing?->priorities     ?? [],
            'status_ids'     => $filters['status_ids']     ?? $existing?->status_ids     ?? [],
            'statuses'       => $filters['statuses']       ?? $existing?->statuses       ?? [],
            'hide_completed' => $filters['hide_completed'] ?? $existing?->hide_completed ?? false,
            'unassigned'     => $filters['unassigned']     ?? $existing?->unassigned     ?? false,
            'view_mode'      => $filters['view_mode']      ?? $existing?->view_mode      ?? 'active',
            'view_sprint_id' => array_key_exists('view_sprint_id', $filters)
                ? $filters['view_sprint_id']
                : $existing?->view_sprint_id,
        ];

        return $this->repository->upsert($userId, $projectId, $data);
    }

    public function saveView(int $userId, int $projectId, string $mode, ?int $sprintId): UserProjectFilter
    {
        return $this->save($userId, $projectId, [
            'view_mode'      => $mode,
            'view_sprint_id' => $mode === 'active' ? $sprintId : null,
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
