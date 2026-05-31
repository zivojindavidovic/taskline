<?php

namespace App\Services;

use App\Models\CommentReply;
use App\Models\Project;
use App\Models\TaskComment;
use App\Models\User;
use App\Repositories\CommentMentionRepository;
use Illuminate\Support\Collection;

/**
 * Handles parsing @mention tokens out of comment bodies and persisting them.
 *
 * Token format expected from the frontend (produced by the autocomplete):
 *
 *     @[Display Name](user:123)
 *
 * The display name is purely cosmetic for the rendered comment; the canonical
 * link is the trailing `user:<id>` segment, which we use to validate the
 * mention against the project's member list and to deduplicate.
 *
 * The author of the comment is *never* mentioned, even if their token appears
 * in the body — UI prevents the case, and we strip it on the backend so a
 * crafted request cannot self-mention.
 */
class CommentMentionService
{
    private const TOKEN_PATTERN = '/@\[[^\]]+\]\(user:(\d+)\)/';

    public function __construct(private CommentMentionRepository $repository) {}

    /**
     * Pull all user IDs out of a body string, in order of appearance.
     *
     * @return array<int, int>
     */
    public function extractMentionedIds(string $body): array
    {
        if (!preg_match_all(self::TOKEN_PATTERN, $body, $matches)) {
            return [];
        }
        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    /**
     * Filter a candidate list to user ids that are:
     *   - members of the project's workspace (or project owner)
     *   - NOT the author
     *
     * @param  array<int>  $candidateIds
     * @return array<int>
     */
    public function filterToProjectMembers(Project $project, int $authorId, array $candidateIds): array
    {
        if (empty($candidateIds)) {
            return [];
        }

        $allowed = $this->mentionableUserIds($project, $authorId);
        return array_values(array_filter($candidateIds, fn ($id) => in_array((int) $id, $allowed, true)));
    }

    /**
     * The set of users a comment-author can mention on this project:
     * project + workspace members minus the author themselves.
     *
     * @return array<int>
     */
    public function mentionableUserIds(Project $project, int $authorId): array
    {
        $workspace = $project->workspace;
        $ids = $workspace->users()->pluck('users.id')->all();
        if ($workspace->owner_id && !in_array((int) $workspace->owner_id, $ids, true)) {
            $ids[] = (int) $workspace->owner_id;
        }
        if ($project->owner_id && !in_array((int) $project->owner_id, $ids, true)) {
            $ids[] = (int) $project->owner_id;
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return array_values(array_filter($ids, fn ($id) => $id !== (int) $authorId));
    }

    /**
     * Convenience: project members ready for autocomplete (id/name/email/avatar).
     *
     * @return Collection<int, array{id:int, name:string, email:string, avatar_color:?string}>
     */
    public function mentionableUsers(Project $project, int $authorId): Collection
    {
        $ids = $this->mentionableUserIds($project, $authorId);
        if (empty($ids)) {
            return collect();
        }
        return User::whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar_color'])
            ->map(fn (User $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'avatar_color' => $u->avatar_color,
            ]);
    }

    /**
     * Parse mentions from the body, filter to allowed users, persist rows,
     * and return the persisted user IDs.
     *
     * @return array<int>
     */
    public function syncForComment(TaskComment $comment, Project $project): array
    {
        $candidates = $this->extractMentionedIds($comment->body);
        $allowed    = $this->filterToProjectMembers($project, (int) $comment->user_id, $candidates);
        $persisted  = $this->repository->syncForComment($comment, $allowed);

        $this->notifyMentioned($persisted);

        return $persisted;
    }

    /**
     * @return array<int>
     */
    public function syncForReply(CommentReply $reply, Project $project): array
    {
        $candidates = $this->extractMentionedIds($reply->body);
        $allowed    = $this->filterToProjectMembers($project, (int) $reply->user_id, $candidates);
        $persisted  = $this->repository->syncForReply($reply, $allowed);

        $this->notifyMentioned($persisted);

        return $persisted;
    }

    /**
     * Ping the inbox of every freshly-mentioned user. The author is already
     * filtered out upstream, so everyone here is someone else. The inbox is
     * derived on read — the event is purely a refresh trigger.
     *
     * @param  array<int>  $userIds
     */
    private function notifyMentioned(array $userIds): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            broadcast(new \App\Events\InboxNotificationSent($userId, 'mention'))->toOthers();
        }
    }
}
