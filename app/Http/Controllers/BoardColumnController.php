<?php

namespace App\Http\Controllers;

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
        $project->boardColumns()->create([
            'name'     => $data['name'],
            'color'    => $data['color'] ?? '#94948c',
            'position' => $position,
        ]);

        return back()->with('success', "Column \"{$data['name']}\" added.");
    }

    public function update(Request $request, BoardColumn $column): RedirectResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
        ]);

        $column->update($data);

        return back();
    }

    public function destroy(BoardColumn $column): RedirectResponse
    {
        if ($column->tasks()->exists()) {
            return back()->withErrors(['column' => 'Cannot delete a column that contains tasks.']);
        }

        $name = $column->name;
        $column->delete();

        return back()->with('success', "Column \"{$name}\" removed.");
    }
}
