<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\FilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FilterController extends Controller
{
    public function __construct(private FilterService $service) {}

    public function show(Project $project): JsonResponse
    {
        $this->authorizeAccess($project);

        $filters = $this->service->get(auth()->id(), $project->id);

        return response()->json($filters);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($project);

        $validated = $request->validate([
            'sprint_ids'     => ['nullable', 'array'],
            'sprint_ids.*'   => ['integer'],
            'assignee_ids'   => ['nullable', 'array'],
            'assignee_ids.*' => ['integer'],
            'priorities'     => ['nullable', 'array'],
            'priorities.*'   => ['string', 'in:low,med,high,urgent'],
            'status_ids'     => ['nullable', 'array'],
            'status_ids.*'   => ['integer'],
            'statuses'       => ['nullable', 'array'],
            'statuses.*'     => ['string', 'in:open,completed'],
            'hide_completed' => ['nullable', 'boolean'],
            'unassigned'     => ['nullable', 'boolean'],
            'view_mode'      => ['nullable', 'string', 'in:active,backlog,all'],
            'view_sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
        ]);

        $filter = $this->service->save(auth()->id(), $project->id, $validated);

        return response()->json([
            'sprint_ids'     => $filter->sprint_ids     ?? [],
            'assignee_ids'   => $filter->assignee_ids   ?? [],
            'priorities'     => $filter->priorities     ?? [],
            'status_ids'     => $filter->status_ids     ?? [],
            'statuses'       => $filter->statuses       ?? [],
            'hide_completed' => $filter->hide_completed ?? false,
            'unassigned'     => $filter->unassigned     ?? false,
            'view_mode'      => $filter->view_mode      ?? 'active',
            'view_sprint_id' => $filter->view_sprint_id,
        ]);
    }

    private function authorizeAccess(Project $project): void
    {
        $user = auth()->user();
        abort_unless(
            $project->owner_id === $user->id ||
            $project->members()->where('users.id', $user->id)->exists(),
            403
        );
    }
}
