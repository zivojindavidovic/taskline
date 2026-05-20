<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;

        // Without a workspace there is nothing to audit yet — render an empty
        // shell with no project/member options.
        if (! $workspace) {
            return Inertia::render('AuditLog', [
                'logs'     => ['data' => [], 'links' => [], 'meta' => ['current_page' => 1, 'last_page' => 1]],
                'projects' => [],
                'members'  => [],
                'filters'  => $this->emptyFilters(),
            ]);
        }

        // Every project the user can see in this workspace — used both for the
        // filter dropdown and for the audit scope below.
        $projectIds = Project::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn ($mq) => $mq->where('users.id', $user->id));
            })
            ->pluck('id');

        $filters = $this->parseFilters($request, $projectIds);

        $query = AuditLog::query()
            ->whereIn('project_id', $projectIds)
            ->with([
                'user:id,name,email,avatar_color',
                'project:id,name,key,color',
                'task:id,key,title',
            ]);

        if ($filters['project_id']) {
            $query->where('project_id', $filters['project_id']);
        }
        if ($filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }
        if ($filters['since']) {
            $query->where('created_at', '>=', $filters['since']);
        }

        $logs = $query->latest()->paginate(50)->withQueryString();

        // Workspace project list for the project filter dropdown.
        $projects = Project::where('workspace_id', $workspace->id)
            ->whereIn('id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'color']);

        // Workspace member list for the person filter dropdown.
        $members = $workspace->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email', 'users.avatar_color']);
        if (! $members->contains('id', $workspace->owner_id)) {
            $members->prepend($workspace->owner()->first(['id', 'name', 'email', 'avatar_color']));
        }

        return Inertia::render('AuditLog', [
            'logs'     => $logs,
            'projects' => $projects,
            'members'  => $members->values(),
            'filters'  => [
                'project_id' => $filters['project_id'],
                'user_id'    => $filters['user_id'],
                'range'      => $filters['range'],
            ],
        ]);
    }

    private function parseFilters(Request $request, $allowedProjectIds): array
    {
        $projectId = $request->integer('project_id') ?: null;
        if ($projectId && ! $allowedProjectIds->contains($projectId)) {
            $projectId = null;
        }

        $userId = $request->integer('user_id') ?: null;
        $range  = $request->string('range')->toString() ?: '7d';

        $since = match ($range) {
            'today' => now()->startOfDay(),
            '7d'    => now()->subDays(7),
            '30d'   => now()->subDays(30),
            'all'   => null,
            default => now()->subDays(7),
        };

        return [
            'project_id' => $projectId,
            'user_id'    => $userId,
            'range'      => in_array($range, ['today', '7d', '30d', 'all'], true) ? $range : '7d',
            'since'      => $since,
        ];
    }

    private function emptyFilters(): array
    {
        return ['project_id' => null, 'user_id' => null, 'range' => '7d'];
    }
}
