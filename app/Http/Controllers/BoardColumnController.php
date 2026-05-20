<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BoardColumnController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        $position = $project->boardColumns()->max('position') + 1;
        $column = $project->boardColumns()->create([
            'name'     => $data['name'],
            'color'    => $data['color'] ?? '#94948c',
            'position' => $position,
        ]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $project->id,
            'action'     => 'column.created',
            'meta'       => ['column' => $column->name],
        ]);

        return back()->with('success', "Column \"{$data['name']}\" added.");
    }

    public function update(Request $request, BoardColumn $column): RedirectResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
        ]);

        $previousName = $column->name;
        $column->update($data);

        if (isset($data['name']) && $data['name'] !== $previousName) {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'project_id' => $column->project_id,
                'action'     => 'column.renamed',
                'meta'       => ['from' => $previousName, 'to' => $column->name],
            ]);
        }

        return back();
    }

    public function destroy(BoardColumn $column): RedirectResponse
    {
        if ($column->tasks()->exists()) {
            return back()->withErrors(['column' => 'Cannot delete a column that contains tasks.']);
        }

        $name      = $column->name;
        $projectId = $column->project_id;
        $column->delete();

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $projectId,
            'action'     => 'column.deleted',
            'meta'       => ['column' => $name],
        ]);

        return back()->with('success', "Column \"{$name}\" removed.");
    }
}
