<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function project(Request $request, Project $project): JsonResponse
    {
        $this->authorizeMember($project);

        $logs = $project->auditLogs()
            ->with('user:id,name,email', 'task:id,key,title')
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }

    public function workspace(Request $request): JsonResponse
    {
        $projectIds = $request->user()
            ->projects()
            ->pluck('projects.id')
            ->merge($request->user()->ownedProjects()->pluck('id'))
            ->unique();

        $logs = AuditLog::whereIn('project_id', $projectIds)
            ->with('user:id,name,email', 'project:id,name,color', 'task:id,key,title')
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }

    private function authorizeMember(Project $project): void
    {
        $user = auth()->user();
        $ok = $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
        abort_unless($ok, 403);
    }
}
