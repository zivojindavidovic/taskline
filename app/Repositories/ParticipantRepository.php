<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the various sources of "people connected to a task" out of the DB.
 *
 * Each method returns a Collection<int, array{user_id:int, ...}>; the service
 * is responsible for hydrating and de-duplicating User models.
 */
class ParticipantRepository
{
    /**
     * @return Collection<int, int>
     */
    public function currentAssigneeIds(Task $task): Collection
    {
        return DB::table('task_assignees')
            ->where('task_id', $task->id)
            ->pluck('user_id');
    }

    /**
     * Anyone who has EVER been assigned to the task (via the audit trail).
     *
     * @return Collection<int, int>
     */
    public function historicalAssigneeIds(Task $task): Collection
    {
        return DB::table('audit_logs')
            ->where('task_id', $task->id)
            ->where('action', 'task.assigned')
            ->get(['user_id', 'meta'])
            ->flatMap(function ($row) {
                $ids = [];
                $meta = is_string($row->meta) ? json_decode($row->meta, true) : (array) $row->meta;
                if (isset($meta['assignee_ids']) && is_array($meta['assignee_ids'])) {
                    $ids = array_merge($ids, $meta['assignee_ids']);
                }
                if (isset($meta['previous_assignee_ids']) && is_array($meta['previous_assignee_ids'])) {
                    $ids = array_merge($ids, $meta['previous_assignee_ids']);
                }
                return $ids;
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function creatorId(Task $task): ?int
    {
        return $task->created_by;
    }

    public function completedById(Task $task): ?int
    {
        return $task->completed_by;
    }

    /**
     * Everyone who has authored an edit-style audit log on the task.
     *
     * @return Collection<int, int>
     */
    public function editorIds(Task $task): Collection
    {
        return DB::table('audit_logs')
            ->where('task_id', $task->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * Everyone who has authored a comment on the task.
     *
     * @return Collection<int, int>
     */
    public function commenterIds(Task $task): Collection
    {
        return DB::table('task_comments')
            ->where('task_id', $task->id)
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * Everyone who has replied to a comment on the task.
     *
     * @return Collection<int, int>
     */
    public function replyAuthorIds(Task $task): Collection
    {
        return DB::table('comment_replies')
            ->join('task_comments', 'comment_replies.task_comment_id', '=', 'task_comments.id')
            ->where('task_comments.task_id', $task->id)
            ->pluck('comment_replies.user_id')
            ->unique()
            ->values();
    }

    /**
     * Users granted task-level access via an approved access request. Surfacing
     * them as participants is the only place an owner can see who holds a grant
     * once the request has left the pending "Access requests" list.
     *
     * @return Collection<int, int>
     */
    public function grantedUserIds(Task $task): Collection
    {
        return DB::table('task_access_requests')
            ->where('task_id', $task->id)
            ->where('status', 'approved')
            ->pluck('user_id')
            ->unique()
            ->values();
    }
}
