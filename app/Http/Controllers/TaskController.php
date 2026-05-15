<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $project = Project::findOrFail($request->input('project_id'));
        $workspaceUserIds = $this->workspaceUserIds($project);

        $data = $request->validate([
            'project_id'      => 'required|exists:projects,id',
            'sprint_id'       => 'nullable|exists:sprints,id',
            'board_column_id' => 'nullable|exists:board_columns,id',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'priority'        => 'required|in:urgent,high,med,low',
            'assignee_id'     => ['nullable', Rule::in($workspaceUserIds)],
            'tags'            => 'nullable|array',
            'tags.*'          => 'string|max:50',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
            'files'           => 'nullable|array',
            'files.*'         => 'file|max:20480',
        ]);

        $taskNum = $project->tasks()->count() + 1;

        $task = Task::create([
            ...collect($data)->except('files')->all(),
            'key'        => $project->key.'-'.$taskNum,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("attachments/task-{$task->id}", 'public');
                TaskAttachment::create([
                    'task_id'       => $task->id,
                    'uploaded_by'   => auth()->id(),
                    'original_name' => $file->getClientOriginalName(),
                    'disk'          => 'public',
                    'path'          => $path,
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        }

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $project->id,
            'task_id'    => $task->id,
            'action'     => 'task.created',
            'meta'       => ['title' => $task->title],
        ]);

        return back()->with('success', "Task {$task->key} created.");
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $workspaceUserIds = $this->workspaceUserIds($task->project);

        $data = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'sometimes|nullable|string',
            'priority'        => 'sometimes|in:urgent,high,med,low',
            'assignee_id'     => ['sometimes', 'nullable', Rule::in(array_merge([null], $workspaceUserIds))],
            'tags'            => 'sometimes|nullable|array',
            'board_column_id' => 'sometimes|nullable|exists:board_columns,id',
            'sprint_id'       => 'sometimes|nullable|exists:sprints,id',
            'project_id'      => 'sometimes|exists:projects,id',
            'start_date'      => 'sometimes|nullable|date',
            'due_date'        => 'sometimes|nullable|date',
        ]);

        // Determine audit action
        $action = null;
        if (isset($data['board_column_id']) && $data['board_column_id'] != $task->board_column_id) {
            $col    = BoardColumn::find($data['board_column_id']);
            $action = 'task.moved';
            $meta   = ['column' => $col?->name];
        } elseif (array_key_exists('assignee_id', $data)) {
            $action = 'task.assigned';
            $meta   = ['assignee_id' => $data['assignee_id']];
        } elseif (isset($data['priority'])) {
            $action = 'task.priority_changed';
            $meta   = ['priority' => $data['priority']];
        } elseif (isset($data['title'])) {
            $action = 'task.renamed';
            $meta   = ['title' => $data['title']];
        } elseif (isset($data['tags'])) {
            $action = 'task.tags_updated';
            $meta   = ['tags' => $data['tags']];
        } else {
            $action = 'task.updated';
            $meta   = [];
        }

        $task->update($data);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => $action,
            'meta'       => $meta ?? [],
        ]);

        return back();
    }

    public function move(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'board_column_id' => 'required|exists:board_columns,id',
        ]);

        $col = BoardColumn::findOrFail($data['board_column_id']);
        $task->update($data);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.moved',
            'meta'       => ['column' => $col->name],
        ]);

        return back();
    }

    public function complete(Task $task): RedirectResponse
    {
        $task->update([
            'completed'    => true,
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.completed',
        ]);

        return back()->with('success', 'Task marked as completed.');
    }

    public function uncomplete(Task $task): RedirectResponse
    {
        $task->update([
            'completed'    => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.reopened',
        ]);

        return back();
    }

    public function storeSubtask(Request $request, Task $parent): RedirectResponse
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'priority' => 'required|in:urgent,high,med,low',
        ]);

        $project = $parent->project;
        $taskNum = $project->tasks()->count() + 1;

        $subtask = Task::create([
            'key'             => $project->key . '-' . $taskNum,
            'title'           => $data['title'],
            'priority'        => $data['priority'],
            'project_id'      => $parent->project_id,
            'sprint_id'       => $parent->sprint_id,
            'board_column_id' => $parent->board_column_id,
            'parent_task_id'  => $parent->id,
            'created_by'      => auth()->id(),
        ]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $parent->project_id,
            'task_id'    => $parent->id,
            'action'     => 'task.subtask_added',
            'meta'       => ['subtask_key' => $subtask->key, 'title' => $subtask->title],
        ]);

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $key = $task->key;
        $task->delete();

        return back()->with('success', "Task {$key} deleted.");
    }

    private function workspaceUserIds(Project $project): array
    {
        $workspace = $project->workspace;
        $ids = $workspace->users()->pluck('users.id')->all();
        if (!in_array($workspace->owner_id, $ids)) {
            $ids[] = $workspace->owner_id;
        }
        return $ids;
    }

    public function myTasks(): Response
    {
        $tasks = Task::where('assignee_id', auth()->id())
            ->with(['project:id,name,key,color', 'boardColumn:id,name,color', 'sprint:id,name'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'med' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'id'          => $t->id,
                'key'         => $t->key,
                'title'       => $t->title,
                'priority'    => $t->priority,
                'completed'   => $t->completed,
                'due_date'    => $t->due_date,
                'project_id'  => $t->project_id,
                'project_key' => $t->project?->key,
                'column_name' => $t->boardColumn?->name,
                'sprint_name' => $t->sprint?->name,
            ]);

        return Inertia::render('MyTasks', ['tasks' => $tasks]);
    }
}
