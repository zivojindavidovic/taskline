<?php

namespace App\Http\Middleware;

use App\Support\Deployment;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Resolve current workspace
        $workspace = null;
        if ($user) {
            $workspace = $user->currentWorkspace
                ?? \App\Models\Workspace::where('owner_id', $user->id)->oldest()->first()
                ?? \App\Models\Workspace::whereHas('users', fn ($q) => $q->where('users.id', $user->id))->oldest()->first();

            // Auto-set if not persisted yet
            if ($workspace && !$user->current_workspace_id) {
                $user->updateQuietly(['current_workspace_id' => $workspace->id]);
            }
        }

        $workspaces = $user
            ? \App\Models\Workspace::where('owner_id', $user->id)
                ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id))
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'owner_id'])
            : collect();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'deployment' => Deployment::resolve($request),
            'workspace'  => $workspace ? $workspace->only(['id', 'name', 'color', 'owner_id']) : null,
            'workspaces' => $workspaces,
            'projects' => $user
                ? \App\Models\Project::where(function ($q) use ($user) {
                        $q->where('owner_id', $user->id)
                          ->orWhereHas('members', fn ($q2) => $q2->where('users.id', $user->id));
                    })
                    ->when($workspace, fn ($q) => $q->where('workspace_id', $workspace->id))
                    ->orderBy('name')
                    ->get(['id', 'uuid', 'name', 'key', 'color'])
                : [],
            'inbox_count' => $user
                ? app(\App\Services\InboxService::class)->build($user)->count()
                : 0,
            'my_tasks_count' => $user
                ? \App\Models\Task::where('assignee_id', $user->id)
                    ->where('completed', false)
                    ->count()
                : 0,
            'flash' => [
                'success'     => $request->session()->get('success'),
                'error'       => $request->session()->get('error'),
                // One-time credential reveal after a self-hosted account is created.
                'createdCred' => $request->session()->get('createdCred'),
            ],
        ];
    }

}
