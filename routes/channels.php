<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private project channel — only members and owners can subscribe
Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    $project = Project::find($projectId);

    if (! $project) {
        return false;
    }

    return $project->owner_id === $user->id
        || $project->members()->where('user_id', $user->id)->exists();
});
