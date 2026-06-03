<?php

namespace App\Http\Controllers;

use App\Events\MemberProjectAccessUpdated;
use App\Events\ProjectCreated;
use App\Models\Project;
use App\Models\User;
use App\Services\FilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private FilterService $filterService) {}

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(
            $user->current_workspace_id !== null,
            422,
            'You must create a workspace before adding projects.'
        );

        // Creating projects is reserved for workspace owners and admins.
        abort_unless(
            $user->currentWorkspace?->canManage($user),
            403,
            'Only workspace owners and admins can create projects.'
        );

        $workspaceId = $user->current_workspace_id;

        // Project name and key must be unique within the workspace — two
        // projects called "Mobile App" (or sharing the "MOB" key) in the same
        // workspace are ambiguous in the sidebar, task keys, and search.
        $data = $request->validate([
            'name'  => [
                'required', 'string', 'max:100',
                Rule::unique('projects', 'name')->where(fn ($q) => $q->where('workspace_id', $workspaceId)),
            ],
            'key'   => [
                'required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('projects', 'key')->where(fn ($q) => $q->where('workspace_id', $workspaceId)),
            ],
            'color' => 'required|string|max:7',
        ], [
            'name.unique' => 'A project with this name already exists in this workspace.',
            'key.unique'  => 'A project with this key already exists in this workspace.',
        ]);

        $project = Project::create([
            ...$data,
            'owner_id'     => $user->id,
            'workspace_id' => $user->current_workspace_id,
        ]);

        // Every workspace member except viewers gets immediate access to a new
        // project — viewers are read-only and must be granted access explicitly.
        // The creator is the owner; the workspace owner is an admin; everyone
        // else mirrors their workspace role (admins → admin, members → member).
        $workspace        = $user->currentWorkspace;
        $workspaceOwnerId = $workspace?->owner_id;

        $grantees = $workspace ? $workspace->users()->get() : collect();

        $memberRows = [];
        foreach ($grantees as $member) {
            $wsRole = $member->pivot->role ?? 'member';
            if ($wsRole === 'viewer') {
                continue; // viewers are read-only; no automatic project access
            }
            if ((int) $member->id === (int) $user->id) {
                continue; // creator already attached below as owner
            }
            $isAdmin = $member->id === $workspaceOwnerId || $wsRole === 'admin';
            $memberRows[$member->id] = ['role' => $isAdmin ? 'admin' : 'member'];
        }

        // Always keep the workspace owner reachable, even if the pivot query
        // above missed them (e.g. owner stored only on workspaces.owner_id).
        if ($workspaceOwnerId && $workspaceOwnerId !== $user->id) {
            $memberRows[$workspaceOwnerId] = ['role' => 'admin'];
        }

        if ($memberRows) {
            $project->members()->syncWithoutDetaching($memberRows);
        }

        // Default columns
        foreach ([
            ['name' => 'Todo',        'color' => '#94948c', 'position' => 0],
            ['name' => 'In Progress', 'color' => '#d97706', 'position' => 1],
            ['name' => 'Review',      'color' => '#7c3aed', 'position' => 2],
            ['name' => 'Done',        'color' => '#16a34a', 'position' => 3],
        ] as $col) {
            $project->boardColumns()->create($col);
        }

        // No default sprint is created — a fresh project starts with only its
        // backlog (tasks with sprint_id = NULL). Sprints are created explicitly
        // by the user, matching the prototype where a new board has no sprint.

        \App\Models\AuditLog::create([
            'user_id'    => $user->id,
            'project_id' => $project->id,
            'action'     => 'project.created',
            'meta'       => ['name' => $project->name],
        ]);

        broadcast(new ProjectCreated($project))->toOthers();

        // Tell each newly-granted member their project access changed so their
        // sidebar picks up the new project live (AppLayout listens for this on
        // their user channel and reloads the shared `projects` prop). Reusing
        // MemberProjectAccessUpdated avoids a second listener on the workspace
        // channel, which AppLayout doesn't own.
        foreach (array_keys($memberRows) as $memberId) {
            $projectIds = Project::query()
                ->where('workspace_id', $workspaceId)
                ->where(function ($q) use ($memberId) {
                    $q->where('owner_id', $memberId)
                      ->orWhereHas('members', fn ($q2) => $q2->where('users.id', $memberId));
                })
                ->pluck('id')
                ->all();

            broadcast(new MemberProjectAccessUpdated(
                (int) $workspaceId,
                (int) $memberId,
                $projectIds,
            ))->toOthers();
        }

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

        $savedFilters = $this->filterService->get($user->id, $project->id);

        $hasViewQuery = $request->has('sprint') || $request->has('backlog') || $request->has('all');

        if ($hasViewQuery) {
            // The URL exposes the sprint's uuid, never its integer id — resolve
            // it back to the internal id the rest of this method works with.
            $sprintUuid = $request->query('sprint');
            $sprintId   = $sprintUuid
                ? $project->sprints()->where('uuid', $sprintUuid)->value('id')
                : null;
            $isBacklog = $request->query('backlog') === '1';
            $isAll     = $request->query('all') === '1';
        } else {
            // No explicit view in the URL → restore the last view this user
            // selected for this project (sprint / backlog / all).
            $isBacklog = ($savedFilters['view_mode'] ?? null) === 'backlog';
            $isAll     = ($savedFilters['view_mode'] ?? null) === 'all';
            $sprintId  = (!$isBacklog && !$isAll) ? ($savedFilters['view_sprint_id'] ?? null) : null;
        }

        $sprint = null;
        if (!$isBacklog && !$isAll) {
            $sprint = $sprintId
                ? $project->sprints()->find($sprintId)
                : null;
            if (!$sprint) {
                // Stored sprint was deleted, or no preference yet — fall back
                // to active, then latest.
                $sprint = $project->sprints()->where('status', 'active')->first()
                    ?? $project->sprints()->latest()->first();
            }
        }

        // Persist whichever view we are about to render so refresh / logout
        // restore the same selection. Skip if the project has no sprints AND
        // we're trying to save an active-sprint view with no sprint id.
        $viewMode = $isAll ? 'all' : ($isBacklog ? 'backlog' : 'active');
        $viewSprintId = $viewMode === 'active' ? ($sprint?->id) : null;
        if (
            ($savedFilters['view_mode'] ?? null) !== $viewMode ||
            ($savedFilters['view_sprint_id'] ?? null) !== $viewSprintId
        ) {
            $this->filterService->saveView($user->id, $project->id, $viewMode, $viewSprintId);
            $savedFilters['view_mode'] = $viewMode;
            $savedFilters['view_sprint_id'] = $viewSprintId;
        }

        $columns = $project->boardColumns()->orderBy('position')->get();
        $sprints = $project->sprints()->withCount('tasks')->orderByDesc('created_at')->get();

        $taskQuery = $project->tasks()
            ->whereNull('parent_task_id')
            ->with([
                'assignee:id,name,email,avatar_color',
                'assignees:id,name,email,avatar_color',
                'boardColumn:id,name,color',
                'sprint:id,name,status',
                'comments' => fn ($q) => $q->oldest(),
                'comments.user:id,name,email,avatar_color',
                'comments.mentionedUsers:id,name,email,avatar_color',
                'comments.replies' => fn ($q) => $q->oldest(),
                'comments.replies.user:id,name,email,avatar_color',
                'comments.replies.mentionedUsers:id,name,email,avatar_color',
                'auditLogs' => fn ($q) => $q->oldest(),
                'auditLogs.user:id,name,avatar_color',
                'activities' => fn ($q) => $q->oldest(),
                'activities.user:id,name,email,avatar_color',
                'activities.subtask:id,key,title',
                'completedByUser:id,name,avatar_color',
                'subtasks' => fn ($q) => $q->with([
                    'assignee:id,name,email,avatar_color',
                    'assignees:id,name,email,avatar_color',
                    'boardColumn:id,name,color',
                    'comments' => fn ($q2) => $q2->oldest(),
                    'comments.user:id,name,email,avatar_color',
                    'comments.mentionedUsers:id,name,email,avatar_color',
                    'comments.replies' => fn ($q2) => $q2->oldest(),
                    'comments.replies.user:id,name,email,avatar_color',
                ]),
                'attachments' => fn ($q) => $q->latest(),
                'attachments.uploader:id,name,avatar_color',
            ]);

        if ($isAll) {
            $tasks = $taskQuery->get();
        } elseif ($isBacklog) {
            $tasks = $taskQuery->whereNull('sprint_id')->get();
        } else {
            $tasks = $sprint ? $taskQuery->where('sprint_id', $sprint->id)->get() : collect();
        }

        $workspace = $project->workspace;
        $members = $workspace->users()->get(['users.id', 'users.name', 'users.email', 'users.avatar_color']);
        $owner = $workspace->owner;
        if (!$members->contains('id', $owner->id)) {
            $members = collect([[
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'avatar_color' => $owner->avatar_color,
            ]])->concat($members);
        }
        $allUsers = $members->map(fn ($m) => is_array($m) ? $m : $m->only(['id', 'name', 'email', 'avatar_color']));

        $allProjects = $workspace->projects()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'key', 'color']);

        return Inertia::render('Projects/Show', [
            'project'       => $project->only(['id', 'uuid', 'name', 'key', 'color']),
            'currentSprint' => $sprint,
            'isBacklog'     => $isBacklog,
            'isAll'         => $isAll,
            'sprints'       => $sprints,
            'columns'       => $columns,
            'tasks'         => $tasks,
            'allUsers'      => $allUsers,
            'allProjects'   => $allProjects,
            'savedFilters'  => $savedFilters,
        ]);
    }
}
