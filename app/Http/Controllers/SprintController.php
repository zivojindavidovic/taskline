<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $data['start_date'] ??= now()->toDateString();
        $data['end_date']   ??= now()->addDays(14)->toDateString();

        $sprint = $project->sprints()->create([...$data, 'status' => 'planned']);

        return back()->with('success', "Sprint \"{$sprint->name}\" created.");
    }

    public function lock(Sprint $sprint): RedirectResponse
    {
        $sprint->update(['locked' => true]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.locked',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        return back()->with('success', "{$sprint->name} locked. Tasks are now read-only.");
    }

    public function unlock(Sprint $sprint): RedirectResponse
    {
        $sprint->update(['locked' => false]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.unlocked',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        return back()->with('success', "{$sprint->name} unlocked.");
    }

    public function complete(Sprint $sprint): RedirectResponse
    {
        abort_if($sprint->status === 'completed', 422, 'Sprint is already completed.');

        $sprint->update(['status' => 'completed']);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.completed',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        return back()->with('success', "{$sprint->name} completed.");
    }

    public function reopen(Sprint $sprint): RedirectResponse
    {
        abort_if($sprint->status !== 'completed', 422, 'Sprint is not completed.');

        $sprint->update(['status' => 'active']);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.reopened',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        return back()->with('success', "{$sprint->name} reopened.");
    }
}
