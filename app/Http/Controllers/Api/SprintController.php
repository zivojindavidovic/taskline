<?php

namespace App\Http\Controllers\Api;

use App\Events\SprintLocked;
use App\Events\SprintUnlocked;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorizeMember($project);

        return response()->json($project->sprints()->withCount('tasks')->get());
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeMember($project);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'     => 'in:planned,active,completed',
        ]);

        $sprint = $project->sprints()->create($validated);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'project_id' => $project->id,
            'action'     => 'sprint.created',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        return response()->json($sprint, 201);
    }

    public function update(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($sprint->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'     => 'sometimes|in:planned,active,completed',
        ]);

        $sprint->update($validated);

        return response()->json($sprint);
    }

    public function lock(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($sprint->project_id !== $project->id, 404);
        abort_if($sprint->locked, 422, 'Sprint is already locked.');

        $sprint->update(['locked' => true]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'project_id' => $project->id,
            'action'     => 'sprint.locked',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintLocked($sprint, $request->user()))->toOthers();

        return response()->json($sprint);
    }

    public function unlock(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($sprint->project_id !== $project->id, 404);
        abort_if(! $sprint->locked, 422, 'Sprint is not locked.');

        $sprint->update(['locked' => false]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'project_id' => $project->id,
            'action'     => 'sprint.unlocked',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintUnlocked($sprint, $request->user()))->toOthers();

        return response()->json($sprint);
    }

    public function destroy(Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($sprint->project_id !== $project->id, 404);
        $sprint->delete();

        return response()->json(null, 204);
    }

    private function authorizeMember(Project $project): void
    {
        $user = auth()->user();
        $ok = $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
        abort_unless($ok, 403);
    }
}
