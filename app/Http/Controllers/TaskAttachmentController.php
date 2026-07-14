<?php

namespace App\Http\Controllers;

use App\Events\TaskUpdated;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store("attachments/task-{$task->id}", 'public');

        $task->attachments()->create([
            'uploaded_by'   => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'disk'          => 'public',
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ]);

        $this->broadcastAttachmentChange($task);

        return back();
    }

    public function destroy(TaskAttachment $attachment): RedirectResponse
    {
        $task = $attachment->task;
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        $this->broadcastAttachmentChange($task);

        return back();
    }

    /**
     * Broadcast the top-level task so every viewer receives an attachment
     * change, including an attachment that belongs to a nested subtask.
     */
    private function broadcastAttachmentChange(Task $task): void
    {
        while ($task->parent_task_id) {
            $task = $task->parent()->firstOrFail();
        }

        $task->load([
            'assignee:id,name,email,avatar_color',
            'assignees:id,name,email,avatar_color',
            'attachments' => fn ($q) => $q->latest(),
            'attachments.uploader:id,name,avatar_color',
        ]);
        $task->loadSubtaskTree();

        broadcast(new TaskUpdated($task))->toOthers();
    }
}
