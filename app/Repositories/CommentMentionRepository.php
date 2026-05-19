<?php

namespace App\Repositories;

use App\Models\CommentMention;
use App\Models\CommentReply;
use App\Models\TaskComment;

class CommentMentionRepository
{
    /**
     * Replace the set of mentions for a comment with the given user IDs.
     *
     * @param  array<int>  $userIds
     * @return array<int>  Persisted user ids.
     */
    public function syncForComment(TaskComment $comment, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        CommentMention::where('task_comment_id', $comment->id)->delete();
        foreach ($userIds as $id) {
            CommentMention::create([
                'task_comment_id' => $comment->id,
                'user_id'         => $id,
            ]);
        }
        return $userIds;
    }

    /**
     * Replace the set of mentions for a reply with the given user IDs.
     *
     * @param  array<int>  $userIds
     * @return array<int>  Persisted user ids.
     */
    public function syncForReply(CommentReply $reply, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        CommentMention::where('comment_reply_id', $reply->id)->delete();
        foreach ($userIds as $id) {
            CommentMention::create([
                'comment_reply_id' => $reply->id,
                'user_id'          => $id,
            ]);
        }
        return $userIds;
    }
}
