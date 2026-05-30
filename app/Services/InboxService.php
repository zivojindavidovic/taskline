<?php

namespace App\Services;

use App\Models\CommentMention;
use App\Models\CommentReply;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for a user's inbox.
 *
 * Aggregates real activity that concerns the logged-in user from the existing
 * tables (comments, replies, @-mentions, assignee changes, status changes) —
 * there is no separate notifications store; the inbox is derived on read, the
 * same way the prototype derives it from tasks. Both the Inbox page and the
 * sidebar badge count call build() so they never disagree.
 *
 * An item the user can see is one where the *actor is someone else*. Events the
 * user performed themselves are never surfaced.
 */
class InboxService
{
    /** Max items returned by the inbox list. */
    private const LIMIT = 30;

    /**
     * Build the ordered notification feed for the given user.
     *
     * @return Collection<int, array>
     */
    public function build(User $user): Collection
    {
        $userId = $user->id;
        $participantIds = $this->participantTaskIds($userId);

        return collect()
            ->concat($this->mentions($userId))
            ->concat($this->assigned($userId))
            ->concat($this->comments($userId, $participantIds))
            ->concat($this->statusChanges($userId, $participantIds))
            ->sortByDesc('created_at')
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * Tasks the user is a participant of: assignee (current or legacy primary),
     * creator, or has commented on. These are the tasks whose comments and
     * status changes are relevant to them.
     */
    private function participantTaskIds(int $userId): Collection
    {
        return collect()
            ->merge(DB::table('task_assignees')->where('user_id', $userId)->pluck('task_id'))
            ->merge(Task::where('assignee_id', $userId)->pluck('id'))
            ->merge(Task::where('created_by', $userId)->pluck('id'))
            ->merge(TaskComment::where('user_id', $userId)->pluck('task_id'))
            ->unique()
            ->values();
    }

    /** Someone @-mentioned the user in a comment or reply. */
    private function mentions(int $userId): Collection
    {
        return CommentMention::where('user_id', $userId)
            ->with([
                'taskComment.user:id,name',
                'taskComment.task:id,key,title,project_id',
                'taskComment.task.project:id,key',
                'commentReply.user:id,name',
                'commentReply.comment.task:id,key,title,project_id',
                'commentReply.comment.task.project:id,key',
            ])
            ->latest()
            ->take(40)
            ->get()
            ->map(function (CommentMention $m) {
                $source = $m->taskComment ?: $m->commentReply;
                $task   = $m->taskComment
                    ? $m->taskComment->task
                    : $m->commentReply?->comment?->task;

                if (! $source || ! $task || $source->user_id === $m->user_id) {
                    return null; // self-mention or orphaned row
                }

                return $this->item(
                    'm-'.$m->id,
                    'mention',
                    $source->user?->name,
                    'mentioned you on',
                    $task,
                    $this->clean($source->body),
                    $m->created_at,
                );
            })
            ->filter();
    }

    /** Someone added the user as an assignee (FIELD_ASSIGNEES gaining their id). */
    private function assigned(int $userId): Collection
    {
        return TaskActivity::where('field', TaskActivity::FIELD_ASSIGNEES)
            ->where('user_id', '!=', $userId)
            ->with(['user:id,name', 'task:id,key,title,project_id', 'task.project:id,key'])
            ->latest()
            ->take(80)
            ->get()
            ->filter(function (TaskActivity $a) use ($userId) {
                $to   = $a->to_value['ids']   ?? [];
                $from = $a->from_value['ids'] ?? [];

                // Surface only the activity that *added* this user.
                return in_array($userId, $to) && ! in_array($userId, $from);
            })
            ->map(fn (TaskActivity $a) => $a->task ? $this->item(
                'a-'.$a->id,
                'assigned',
                $a->user?->name,
                'assigned you',
                $a->task,
                $a->task->title,
                $a->created_at,
            ) : null)
            ->filter();
    }

    /**
     * Comments and replies on tasks the user participates in, by someone else.
     * Comments that mention the user are skipped — the mention is the stronger
     * signal and already covers them.
     */
    private function comments(int $userId, Collection $participantIds): Collection
    {
        $comments = TaskComment::whereIn('task_id', $participantIds)
            ->where('user_id', '!=', $userId)
            ->whereDoesntHave('mentions', fn ($q) => $q->where('user_id', $userId))
            ->with(['user:id,name', 'task:id,key,title,project_id', 'task.project:id,key'])
            ->latest()
            ->take(40)
            ->get()
            ->map(fn (TaskComment $c) => $c->task ? $this->item(
                'c-'.$c->id,
                'comment',
                $c->user?->name,
                'commented on',
                $c->task,
                $this->clean($c->body),
                $c->created_at,
            ) : null)
            ->filter();

        $replies = CommentReply::whereHas('comment', fn ($q) => $q->whereIn('task_id', $participantIds))
            ->where('user_id', '!=', $userId)
            ->whereDoesntHave('mentions', fn ($q) => $q->where('user_id', $userId))
            ->with(['user:id,name', 'comment.task:id,key,title,project_id', 'comment.task.project:id,key'])
            ->latest()
            ->take(40)
            ->get()
            ->map(function (CommentReply $r) {
                $task = $r->comment?->task;

                return $task ? $this->item(
                    'r-'.$r->id,
                    'comment',
                    $r->user?->name,
                    'commented on',
                    $task,
                    $this->clean($r->body),
                    $r->created_at,
                ) : null;
            })
            ->filter();

        return $comments->concat($replies);
    }

    /** Someone marked a participant task complete or reopened it. */
    private function statusChanges(int $userId, Collection $participantIds): Collection
    {
        return TaskActivity::where('field', TaskActivity::FIELD_STATUS)
            ->whereIn('task_id', $participantIds)
            ->where('user_id', '!=', $userId)
            ->with(['user:id,name', 'task:id,key,title,project_id', 'task.project:id,key'])
            ->latest()
            ->take(40)
            ->get()
            ->map(function (TaskActivity $a) {
                if (! $a->task) {
                    return null;
                }
                $completed = (bool) ($a->to_value['value'] ?? false);

                return $this->item(
                    's-'.$a->id,
                    'status',
                    $a->user?->name,
                    $completed ? 'completed' : 'reopened',
                    $a->task,
                    $a->task->title,
                    $a->created_at,
                );
            })
            ->filter();
    }

    /** Shape one notification row for the frontend. */
    private function item(string $id, string $type, ?string $actor, string $verb, Task $task, ?string $excerpt, $createdAt): array
    {
        return [
            'id'         => $id,
            'type'       => $type,
            'actor'      => $actor ?? 'Someone',
            'verb'       => $verb,
            'target'     => $task->key,
            'excerpt'    => $excerpt,
            'time'       => $createdAt?->diffForHumans(),
            'task_id'    => $task->id,
            'project_id' => $task->project_id,
            'created_at' => $createdAt,
        ];
    }

    /** Turn @[Display Name](user:123) mention tokens into a readable @Name. */
    private function clean(?string $body): string
    {
        if (! $body) {
            return '';
        }

        return trim(preg_replace('/@\[(.+?)\]\(user:\d+\)/', '@$1', $body));
    }
}
