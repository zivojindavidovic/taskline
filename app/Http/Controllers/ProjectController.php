<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'key'   => 'required|string|max:6|regex:/^[A-Z0-9]+$/',
            'color' => 'required|string|max:7',
        ]);

        $user = auth()->user();

        abort_unless(
            $user->current_workspace_id !== null,
            422,
            'You must create a workspace before adding projects.'
        );

        $project = Project::create([
            ...$data,
            'owner_id'     => $user->id,
            'workspace_id' => $user->current_workspace_id,
        ]);

        // Default columns
        foreach ([
            ['name' => 'Todo',        'color' => '#94948c', 'position' => 0],
            ['name' => 'In Progress', 'color' => '#d97706', 'position' => 1],
            ['name' => 'Review',      'color' => '#7c3aed', 'position' => 2],
            ['name' => 'Done',        'color' => '#16a34a', 'position' => 3],
        ] as $col) {
            $project->boardColumns()->create($col);
        }

        // Default sprint
        $project->sprints()->create([
            'name'       => 'Sprint 1',
            'start_date' => now(),
            'end_date'   => now()->addDays(14),
            'status'     => 'active',
        ]);

        \App\Models\AuditLog::create([
            'user_id'    => $user->id,
            'project_id' => $project->id,
            'action'     => 'project.created',
            'meta'       => ['name' => $project->name],
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', "Project \"{$project->name}\" created.");
    }

    public function show(Request $request, Project $project): Response
    {
        $user = auth()->user();
        abort_unless(
            $project->owner_id === $user->id ||
            $project->members()->where('users.id', $user->id)->exists(),
            403
        );

        $sprintId = $request->query('sprint');
        $isBacklog = $request->query('backlog') === '1';

        $sprint = null;
        if (!$isBacklog) {
            $sprint = $sprintId
                ? $project->sprints()->findOrFail($sprintId)
                : $project->sprints()->where('status', 'active')->first()
                    ?? $project->sprints()->latest()->first();
        }

        $columns = $project->boardColumns()->orderBy('position')->get();
        $sprints = $project->sprints()->orderByDesc('created_at')->get();

        $taskQuery = $project->tasks()
            ->whereNull('parent_task_id')
            ->with([
                'assignee:id,name,email',
                'boardColumn:id,name,color',
                'sprint:id,name,status',
                'comments' => fn ($q) => $q->oldest(),
                'comments.user:id,name,email',
                'comments.replies' => fn ($q) => $q->oldest(),
                'comments.replies.user:id,name,email',
                'auditLogs' => fn ($q) => $q->oldest(),
                'auditLogs.user:id,name',
                'completedByUser:id,name',
                'subtasks' => fn ($q) => $q->with(['assignee:id,name,email', 'boardColumn:id,name,color']),
            ]);

        $tasks = $isBacklog
            ? $taskQuery->whereNull('sprint_id')->get()
            : ($sprint ? $taskQuery->where('sprint_id', $sprint->id)->get() : collect());

        $workspace = $project->workspace;
        $members = $workspace->users()->get(['users.id', 'users.name', 'users.email']);
        $owner = $workspace->owner;
        if (!$members->contains('id', $owner->id)) {
            $members = collect([['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email]])->concat($members);
        }
        $allUsers = $members->map(fn ($m) => is_array($m) ? $m : $m->only(['id', 'name', 'email']));

        return Inertia::render('Projects/Show', [
            'project'       => $project->only(['id', 'name', 'key', 'color']),
            'currentSprint' => $sprint,
            'isBacklog'     => $isBacklog,
            'sprints'       => $sprints,
            'columns'       => $columns,
            'tasks'         => $tasks,
            'allUsers'      => $allUsers,
        ]);
    }
}
