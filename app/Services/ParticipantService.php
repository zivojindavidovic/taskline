<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Repositories\ParticipantRepository;
use Illuminate\Support\Collection;

/**
 * Resolves the *participants* of a task: anyone who is or was an assignee,
 * created the task, completed it, edited it, commented, or replied.
 *
 * Each returned entry includes the user model and the set of roles that
 * caused them to count as a participant.
 */
class ParticipantService
{
    public function __construct(private ParticipantRepository $repository) {}

    /**
     * @return Collection<int, array{user: User, roles: array<int, string>}>
     */
    public function forTask(Task $task): Collection
    {
        /** @var array<int, array<int, string>> $rolesByUser */
        $rolesByUser = [];

        $add = function (?int $userId, string $role) use (&$rolesByUser): void {
            if (!$userId) {
                return;
            }
            $rolesByUser[$userId] ??= [];
            if (!in_array($role, $rolesByUser[$userId], true)) {
                $rolesByUser[$userId][] = $role;
            }
        };

        foreach ($this->repository->currentAssigneeIds($task) as $id) {
            $add((int) $id, 'assignee');
        }
        foreach ($this->repository->historicalAssigneeIds($task) as $id) {
            $add((int) $id, 'past_assignee');
        }
        $add($this->repository->creatorId($task), 'reporter');
        $add($this->repository->completedById($task), 'completer');
        foreach ($this->repository->editorIds($task) as $id) {
            $add((int) $id, 'editor');
        }
        foreach ($this->repository->commenterIds($task) as $id) {
            $add((int) $id, 'commenter');
        }
        foreach ($this->repository->replyAuthorIds($task) as $id) {
            $add((int) $id, 'commenter');
        }

        if (empty($rolesByUser)) {
            return collect();
        }

        $users = User::whereIn('id', array_keys($rolesByUser))->get()->keyBy('id');

        return collect($rolesByUser)
            ->map(fn (array $roles, int $userId) => [
                'user'  => $users[$userId] ?? null,
                'roles' => $this->sortRoles($roles),
            ])
            ->filter(fn ($entry) => $entry['user'] !== null)
            ->values();
    }

    /**
     * Stable, presentation-friendly role order.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function sortRoles(array $roles): array
    {
        $order = ['reporter', 'assignee', 'past_assignee', 'completer', 'editor', 'commenter'];
        usort($roles, fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));
        return $roles;
    }
}
