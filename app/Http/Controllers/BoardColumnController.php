<?php

namespace App\Http\Controllers;

use App\Events\BoardColumnUpdated;
use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        broadcast(new BoardColumnUpdated($project, $column, 'created'))->toOthers();

        return back()->with('success', "Column \"{$data['name']}\" added.");
    }

    /**
     * Persist a new left-to-right column order for a project's board.
     *
     * The client sends the full ordered list of column UUIDs (`order`); the
     * server rewrites each column's `position` to match its index. The same
     * endpoint backs both drag-to-reorder and the "Move left / Move right"
     * menu actions — the frontend just computes the resulting order and posts
     * the whole array, which keeps the operation atomic and idempotent.
     */
    public function reorder(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['string'],
        ]);

        $columns = $project->boardColumns()->get();
        $byUuid  = $columns->keyBy('uuid');
        $order   = collect($validated['order']);

        // The submitted order must reference every column in this project
        // exactly once — no duplicates, no missing columns, and nothing from
        // another board. This prevents position corruption and cross-project
        // tampering through the {project} the request was scoped to.
        $isComplete = $order->count() === $columns->count()
            && $order->unique()->count() === $order->count()
            && $order->every(fn ($uuid) => $byUuid->has($uuid));

        if (! $isComplete) {
            throw ValidationException::withMessages([
                'order' => 'The order must list every column in this project exactly once.',
            ]);
        }

        DB::transaction(function () use ($order, $byUuid) {
            $order->each(function ($uuid, $index) use ($byUuid) {
                $column = $byUuid->get($uuid);
                if ((int) $column->position !== $index) {
                    $column->update(['position' => $index]);
                }
            });
        });

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $project->id,
            'action'     => 'column.reordered',
            'meta'       => ['order' => $order->all()],
        ]);

        broadcast(new BoardColumnUpdated($project, $byUuid->get($order->first())->refresh(), 'reordered'))->toOthers();

        return back();
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

        broadcast(new BoardColumnUpdated($column->project, $column->refresh(), 'updated'))->toOthers();

        return back();
    }

    public function destroy(BoardColumn $column): RedirectResponse
    {
        if ($column->tasks()->exists()) {
            return back()->withErrors(['column' => 'Cannot delete a column that contains tasks.']);
        }

        $name      = $column->name;
        $projectId = $column->project_id;
        $project   = $column->project;
        $column->delete();

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $projectId,
            'action'     => 'column.deleted',
            'meta'       => ['column' => $name],
        ]);

        // The column instance still carries its attributes after delete(), so
        // listeners can read the id to splice it out of the board.
        broadcast(new BoardColumnUpdated($project, $column, 'deleted'))->toOthers();

        return back()->with('success', "Column \"{$name}\" removed.");
    }
}
