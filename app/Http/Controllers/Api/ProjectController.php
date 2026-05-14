<?php

namespace App\Http\Controllers\Api;

use App\Events\ProjectUpdated;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()
            ->projects()
            ->with(['sprints', 'boardColumns'])
            ->get()
            ->merge($request->user()->ownedProjects()->with(['sprints', 'boardColumns'])->get())
            ->unique('id')
            ->values();

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'key'   => 'required|string|max:5|alpha',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $project = Project::create([
            ...$validated,
            'key'      => strtoupper($validated['key']),
            'owner_id' => $request->user()->id,
        ]);

        // Add owner as member
        $project->members()->attach($request->user()->id, ['role' => 'owner']);

        // Seed default columns
        $defaults = [
            ['name' => 'Todo', 'color' => '#94948c', 'position' => 0],
            ['name' => 'In Progress', 'color' => '#d97706', 'position' => 1],
            ['name' => 'Review', 'color' => '#7c3aed', 'position' => 2],
            ['name' => 'Done', 'color' => '#16a34a', 'position' => 3],
        ];
        foreach ($defaults as $col) {
            $project->boardColumns()->create($col);
        }

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'project_id' => $project->id,
            'action'     => 'project.created',
            'meta'       => ['name' => $project->name],
        ]);

        return response()->json($project->load(['sprints', 'boardColumns']), 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json($project->load(['sprints', 'boardColumns', 'members']));
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $project->update($validated);

        broadcast(new ProjectUpdated($project))->toOthers();

        return response()->json($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorizeProject($project, ownerOnly: true);
        $project->delete();

        return response()->json(null, 204);
    }

    private function authorizeProject(Project $project, bool $ownerOnly = false): void
    {
        $user = auth()->user();
        $isMember = $project->members()->where('user_id', $user->id)->exists()
            || $project->owner_id === $user->id;

        if (! $isMember || ($ownerOnly && $project->owner_id !== $user->id)) {
            abort(403);
        }
    }
}
