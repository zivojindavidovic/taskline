<?php

namespace App\Http\Controllers;

use App\Events\SprintUpdated;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    /**
     * Sprint management (create/edit/delete/lock/unlock/complete/reopen) is
     * reserved for workspace owners and admins — workspace members only work
     * with tasks.
     */
    private function authorizeManage(Project $project): void
    {
        $workspace = $project->workspace;

        abort_unless(
            $workspace && $workspace->canManage(auth()->user()),
            403,
            'Only workspace owners and admins can manage sprints.'
        );
    }

    /**
     * Workspace-wide Sprints page: every sprint the viewer can see, grouped by
     * project on the client. Each sprint carries live progress (open sprints)
     * and a completed/incomplete breakdown (completed sprints) derived from its
     * tasks — no snapshot column needed, since completing a sprint leaves its
     * tasks attached.
     */
    public function index(Request $request): Response
    {
        $user      = $request->user();
        $workspace = $user->currentWorkspace;
        $canManage = (bool) ($workspace && $workspace->canManage($user));

        $projects = $workspace
            ? Project::where('workspace_id', $workspace->id)
                ->where(function ($q) use ($user) {
                    $q->where('owner_id', $user->id)
                      ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id));
                })
                ->with(['sprints' => fn ($q) => $q->with('tasks.boardColumn')])
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Sprints', [
            'projects'  => $projects->map(fn (Project $p) => [
                'id'    => $p->id,   // integer id — used for the private broadcast channel
                'uuid'  => $p->uuid,
                'name'  => $p->name,
                'color' => $p->color,
            ])->values(),
            'sprints'   => $projects->flatMap(
                fn (Project $p) => $p->sprints->map(fn (Sprint $s) => $this->serialize($s, $p))
            )->values(),
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeManage($project);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'goal'       => 'nullable|string|max:2000',
        ]);

        $data['start_date'] ??= now()->toDateString();
        $data['end_date']   ??= now()->addDays(14)->toDateString();

        $sprint = $project->sprints()->create([...$data, 'status' => 'planned']);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $project->id,
            'action'     => 'sprint.created',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintUpdated($sprint, 'created'))->toOthers();

        return back()->with('success', "Sprint \"{$sprint->name}\" created.");
    }

    public function update(Request $request, Sprint $sprint): RedirectResponse
    {
        $this->authorizeManage($sprint->project);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'goal'       => 'nullable|string|max:2000',
        ]);

        $sprint->update($data);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.updated',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintUpdated($sprint, 'updated'))->toOthers();

        return back()->with('success', "Sprint \"{$sprint->name}\" updated.");
    }

    public function destroy(Request $request, Sprint $sprint, TaskService $taskService): RedirectResponse
    {
        $this->authorizeManage($sprint->project);

        $deleteTasks = $request->boolean('delete_tasks');
        $name        = $sprint->name;
        $projectId   = $sprint->project_id;

        DB::transaction(function () use ($sprint, $deleteTasks, $taskService) {
            if ($deleteTasks) {
                // Permanently delete every task in the sprint. Iterate top-level
                // tasks only — each delete cascades its subtask subtree (plus
                // comments, attachments, assignees) via TaskService/repository.
                $sprint->tasks()
                    ->whereNull('parent_task_id')
                    ->get()
                    ->each(fn (Task $t) => $taskService->delete($t, (int) auth()->id()));
            } else {
                // Default (checkbox off): the sprint's tasks fall back to the
                // backlog (sprint_id = NULL) rather than vanish with the sprint.
                Task::where('sprint_id', $sprint->id)->update(['sprint_id' => null]);
            }

            $sprint->delete();
        });

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $projectId,
            'action'     => 'sprint.deleted',
            'meta'       => ['sprint' => $name, 'tasks_deleted' => $deleteTasks],
        ]);

        // Live: broadcast on the project channel so the board and every open
        // Sprints page refresh (the board reload re-pulls tasks → backlog/gone).
        broadcast(new SprintUpdated($sprint, 'deleted'))->toOthers();

        $note = $deleteTasks
            ? ' All of its tasks were permanently deleted.'
            : ' Its tasks moved to the backlog.';

        return back()->with('success', "Sprint \"{$name}\" deleted.{$note}");
    }

    public function lock(Sprint $sprint): RedirectResponse
    {
        $this->authorizeManage($sprint->project);

        $sprint->update(['locked' => true]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.locked',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintUpdated($sprint, 'locked'))->toOthers();

        return back()->with('success', "{$sprint->name} locked. Tasks are now read-only.");
    }

    public function unlock(Sprint $sprint): RedirectResponse
    {
        $this->authorizeManage($sprint->project);

        $sprint->update(['locked' => false]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.unlocked',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintUpdated($sprint, 'unlocked'))->toOthers();

        return back()->with('success', "{$sprint->name} unlocked.");
    }

    public function complete(Sprint $sprint): RedirectResponse
    {
        $this->authorizeManage($sprint->project);

        abort_if($sprint->status === 'completed', 422, 'Sprint is already completed.');

        $sprint->load('tasks.boardColumn');
        $tasks      = $sprint->tasks;
        $done       = $tasks->filter(fn (Task $t) => $this->taskIsDone($t))->values();
        $incomplete = $tasks->reject(fn (Task $t) => $this->taskIsDone($t))->values();
        $total      = $tasks->count();

        // Snapshot the breakdown BEFORE detaching tasks, then roll the
        // unfinished ones back to the backlog (sprint_id = null) — matching the
        // prototype's "Complete sprint" and the overdue-rollover command.
        $sprint->summary = [
            'completed'       => $done->map(fn (Task $t) => ['key' => $t->key, 'title' => $t->title])->values()->all(),
            'incomplete'      => $incomplete->map(fn (Task $t) => ['key' => $t->key, 'title' => $t->title])->values()->all(),
            'completion_rate' => $total ? (int) round($done->count() / $total * 100) : 0,
        ];
        $sprint->status = 'completed';
        $sprint->save();

        if ($incomplete->isNotEmpty()) {
            Task::whereIn('id', $incomplete->pluck('id'))->update(['sprint_id' => null]);
        }

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.completed',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintUpdated($sprint, 'completed'))->toOthers();

        $moved = $incomplete->count();
        $note  = $moved
            ? " {$moved} unfinished task" . ($moved === 1 ? '' : 's') . ' moved to the backlog.'
            : '';

        return back()->with('success', "{$sprint->name} completed.{$note}");
    }

    public function reopen(Sprint $sprint): RedirectResponse
    {
        $this->authorizeManage($sprint->project);

        abort_if($sprint->status !== 'completed', 422, 'Sprint is not completed.');

        // Reopening clears the completion snapshot; tasks that already rolled to
        // the backlog stay there (same as the prototype).
        $sprint->update(['status' => 'active', 'summary' => null]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $sprint->project_id,
            'action'     => 'sprint.reopened',
            'meta'       => ['sprint' => $sprint->name],
        ]);

        broadcast(new SprintUpdated($sprint, 'reopened'))->toOthers();

        return back()->with('success', "{$sprint->name} reopened.");
    }

    /**
     * Shape one sprint for the Sprints page, with task-derived progress.
     * A task counts as "done" when it's completed or sitting in a column named
     * "Done" — the same rule the board and the prototype use.
     */
    private function taskIsDone(Task $t): bool
    {
        return $t->completed
            || (optional($t->boardColumn)->name && strtolower($t->boardColumn->name) === 'done');
    }

    /**
     * Status shown on the Sprints page is derived from the date window: a sprint
     * is "In progress" (active) once its start date has arrived, "Planned" while
     * it is still in the future, and "Done" only when explicitly completed.
     * Falls back to the stored status when there is no start date to derive from.
     */
    private function displayStatus(Sprint $sprint): string
    {
        if ($sprint->status === 'completed') {
            return 'completed';
        }
        if (!$sprint->start_date) {
            return $sprint->status;
        }
        return $sprint->start_date->lessThanOrEqualTo(now()) ? 'active' : 'planned';
    }

    private function serialize(Sprint $sprint, Project $project): array
    {
        $tasks     = $sprint->tasks;
        $done      = $tasks->filter(fn (Task $t) => $this->taskIsDone($t))->values();
        $total     = $tasks->count();
        $doneCount = $done->count();

        $summary = null;
        if ($sprint->status === 'completed') {
            // Prefer the snapshot captured at completion (its tasks have since
            // moved to the backlog). Fall back to a live read for sprints that
            // were completed before snapshots existed.
            if (is_array($sprint->summary) && isset($sprint->summary['completed'])) {
                $summary = [
                    'completed'       => $sprint->summary['completed'] ?? [],
                    'incomplete'      => $sprint->summary['incomplete'] ?? [],
                    'completion_rate' => $sprint->summary['completion_rate'] ?? 0,
                ];
            } else {
                $incomplete = $tasks->reject(fn (Task $t) => $this->taskIsDone($t))->values();
                $summary = [
                    'completed'       => $done->map(fn (Task $t) => ['key' => $t->key, 'title' => $t->title])->values()->all(),
                    'incomplete'      => $incomplete->map(fn (Task $t) => ['key' => $t->key, 'title' => $t->title])->values()->all(),
                    'completion_rate' => $total ? (int) round($doneCount / $total * 100) : 0,
                ];
            }
        }

        return [
            'uuid'         => $sprint->uuid,
            'project_uuid' => $project->uuid,
            'name'         => $sprint->name,
            'start_date'   => optional($sprint->start_date)->toDateString(),
            'end_date'     => optional($sprint->end_date)->toDateString(),
            'goal'         => $sprint->goal,
            'status'       => $this->displayStatus($sprint),
            'locked'       => (bool) $sprint->locked,
            'progress'     => [
                'done'  => $doneCount,
                'total' => $total,
                'pct'   => $total ? (int) round($doneCount / $total * 100) : 0,
            ],
            'summary'      => $summary,
        ];
    }
}
