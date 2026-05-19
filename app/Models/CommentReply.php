<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommentReply extends Model
{
    protected $fillable = ['task_comment_id', 'user_id', 'body'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(CommentMention::class);
    }

    public function mentionedUsers()
    {
        return $this->belongsToMany(User::class, 'comment_mentions', 'comment_reply_id', 'user_id')
            ->withTimestamps();
    }
}
