<?php

namespace App\Http\Controllers\Api;

use App\Events\BoardColumnUpdated;
use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardColumnController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeMember($project);

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $position = $project->boardColumns()->max('position') + 1;
        $column = $project->boardColumns()->create([
            ...$validated,
            'color'    => $validated['color'] ?? '#94948c',
            'position' => $position,
        ]);

        broadcast(new BoardColumnUpdated($project, $column, 'created'))->toOthers();

        return response()->json($column, 201);
    }

    public function update(Request $request, Project $project, BoardColumn $column): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($column->project_id !== $project->id, 404);

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:100',
            'color'    => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'position' => 'sometimes|integer|min:0',
        ]);

        $column->update($validated);

        broadcast(new BoardColumnUpdated($project, $column, 'updated'))->toOthers();

        return response()->json($column);
    }

    public function destroy(Project $project, BoardColumn $column): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($column->project_id !== $project->id, 404);
        abort_if($column->tasks()->exists(), 422, 'Cannot delete a column that has tasks.');

        broadcast(new BoardColumnUpdated($project, $column, 'deleted'))->toOthers();
        $column->delete();

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
