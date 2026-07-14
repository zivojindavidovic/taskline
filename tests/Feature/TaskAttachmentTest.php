<?php

namespace Tests\Feature;

use App\Events\TaskUpdated;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $workspace = Workspace::create([
            'name'     => 'Acme',
            'owner_id' => $this->owner->id,
            'color'    => '#4f46e5',
        ]);
        $workspace->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->owner->update(['current_workspace_id' => $workspace->id]);

        $this->project = Project::create([
            'name'         => 'Mobile App',
            'key'          => 'MOB',
            'color'        => '#4f46e5',
            'owner_id'     => $this->owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $this->project->members()->attach($this->owner->id, ['role' => 'owner']);

        $sprint = Sprint::create(['project_id' => $this->project->id, 'name' => 'Sprint 1']);
        $column = BoardColumn::create([
            'project_id' => $this->project->id,
            'name'       => 'Todo',
            'color'      => '#64748b',
            'position'   => 0,
        ]);
        $this->task = Task::create([
            'key'             => 'MOB-1',
            'title'           => 'Parent task',
            'project_id'      => $this->project->id,
            'sprint_id'       => $sprint->id,
            'board_column_id' => $column->id,
            'created_by'      => $this->owner->id,
            'priority'        => 'med',
        ]);
    }

    public function test_task_attachment_persists_and_is_broadcast_to_other_viewers(): void
    {
        Storage::fake('public');
        Event::fake([TaskUpdated::class]);

        $this->actingAs($this->owner)
            ->post(route('tasks.attachments.store', $this->task), [
                'file' => UploadedFile::fake()->create('brief.pdf', 24, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = $this->task->fresh()->attachments()->sole();
        $this->assertSame('brief.pdf', $attachment->original_name);
        Storage::disk('public')->assertExists($attachment->path);

        Event::assertDispatched(TaskUpdated::class, fn (TaskUpdated $event) =>
            $event->task->id === $this->task->id
            && $event->task->attachments->contains('original_name', 'brief.pdf')
        );
    }

    public function test_subtask_attachment_persists_after_a_task_tree_is_reloaded(): void
    {
        Storage::fake('public');
        Event::fake([TaskUpdated::class]);
        $subtask = Task::create([
            'key'            => 'MOB-1.1',
            'title'          => 'Child task',
            'project_id'     => $this->project->id,
            'sprint_id'      => $this->task->sprint_id,
            'parent_task_id' => $this->task->id,
            'created_by'     => $this->owner->id,
            'priority'       => 'med',
        ]);

        $this->actingAs($this->owner)
            ->post(route('tasks.attachments.store', $subtask), [
                'file' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
            ])
            ->assertRedirect();

        $reloadedTask = $this->task->fresh()->loadSubtaskTree();
        $reloadedSubtask = $reloadedTask->subtasks->sole();
        $this->assertTrue($reloadedSubtask->attachments->contains('original_name', 'notes.txt'));

        Event::assertDispatched(TaskUpdated::class, fn (TaskUpdated $event) =>
            $event->task->id === $this->task->id
            && $event->task->subtasks->sole()->attachments->contains('original_name', 'notes.txt')
        );
    }
}
