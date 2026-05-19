<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentMention extends Model
{
    protected $fillable = [
        'task_comment_id',
        'comment_reply_id',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskComment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class);
    }

    public function commentReply(): BelongsTo
    {
        return $this->belongsTo(CommentReply::class);
    }
}
