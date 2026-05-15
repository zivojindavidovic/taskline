<?php

namespace App\Http\Controllers;

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

        return back();
    }

    public function destroy(TaskAttachment $attachment): RedirectResponse
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back();
    }
}
