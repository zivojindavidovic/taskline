<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMembersController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskAccessRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Invitation accept (auth-optional — controller decides what to do for guests vs users)
Route::get('/invitations/{token}', [InvitationAcceptController::class, 'show'])->name('invitations.accept');

Route::middleware(['auth', 'verified', 'onboarded'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/my-tasks', [TaskController::class, 'myTasks'])->name('my-tasks');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/members', [WorkspaceMembersController::class, 'index'])->name('members');
    Route::patch('/settings/workspace', [SettingsController::class, 'updateWorkspace'])->name('settings.workspace.update');
    Route::delete('/settings/workspace', [SettingsController::class, 'destroyWorkspace'])->name('settings.workspace.destroy');
    Route::post('/settings/members/invite', [WorkspaceMembersController::class, 'invite'])->name('settings.members.invite');
    Route::post('/settings/members/add', [WorkspaceMembersController::class, 'addMember'])->name('settings.members.add');
    Route::patch('/settings/members/{member}/role', [WorkspaceMembersController::class, 'updateRole'])->name('settings.members.role');
    Route::patch('/settings/members/{member}/projects', [WorkspaceMembersController::class, 'updateProjectAccess'])->name('settings.members.projects');
    Route::delete('/settings/members/{member}', [WorkspaceMembersController::class, 'remove'])->name('settings.members.remove');
    Route::patch('/settings/members/invitations/{invitation}/projects', [WorkspaceMembersController::class, 'updateInvitationProjects'])->name('settings.members.invitations.projects');
    Route::delete('/settings/members/invitations/{invitation}', [WorkspaceMembersController::class, 'revoke'])->name('settings.members.revoke');

    // Workspaces
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    // Projects
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/settings', [ProjectController::class, 'settings'])->name('projects.settings');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Filters
    Route::get('/projects/{project}/filters', [FilterController::class, 'show'])->name('projects.filters.show');
    Route::put('/projects/{project}/filters', [FilterController::class, 'update'])->name('projects.filters.update');

    // Members
    Route::get('/projects/{project}/members', [MembersController::class, 'index'])->name('projects.members');
    Route::post('/projects/{project}/members/invite', [MembersController::class, 'invite'])->name('projects.members.invite');
    Route::patch('/projects/{project}/members/{member}/role', [MembersController::class, 'updateRole'])->name('projects.members.role');
    Route::delete('/projects/{project}/members/{member}', [MembersController::class, 'remove'])->name('projects.members.remove');

    // Tasks
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('/tasks/{task}/uncomplete', [TaskController::class, 'uncomplete'])->name('tasks.uncomplete');
    Route::post('/tasks/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('tasks.subtasks.store');
    Route::patch('/tasks/{task}/subtasks/{subtask}', [TaskController::class, 'updateSubtask'])->name('tasks.subtasks.update');
    Route::get('/tasks/{task}/participants', [TaskController::class, 'participants'])->name('tasks.participants');
    Route::get('/tasks/{task}/details', [TaskController::class, 'details'])->name('tasks.details');

    // Task access requests
    Route::get('/tasks/{task}/access-requests', [TaskAccessRequestController::class, 'index'])->name('tasks.access-requests.index');
    Route::post('/tasks/{task}/access-requests', [TaskAccessRequestController::class, 'store'])->name('tasks.access-requests.store');
    Route::post('/tasks/{task}/access-requests/{accessRequest}/approve', [TaskAccessRequestController::class, 'approve'])->name('tasks.access-requests.approve');
    Route::post('/tasks/{task}/access-requests/{accessRequest}/decline', [TaskAccessRequestController::class, 'decline'])->name('tasks.access-requests.decline');
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::delete('/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Comments & replies
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::post('/tasks/{task}/comments/{comment}/replies', [TaskCommentController::class, 'reply'])->name('tasks.comments.reply');
    Route::get('/tasks/{task}/comments/mentionable-users', [TaskCommentController::class, 'mentionableUsers'])->name('tasks.comments.mentionable');

    // Sprints
    Route::post('/projects/{project}/sprints', [SprintController::class, 'store'])->name('sprints.store');
    Route::post('/sprints/{sprint}/lock', [SprintController::class, 'lock'])->name('sprints.lock');
    Route::post('/sprints/{sprint}/unlock', [SprintController::class, 'unlock'])->name('sprints.unlock');
    Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete');
    Route::post('/sprints/{sprint}/reopen', [SprintController::class, 'reopen'])->name('sprints.reopen');

    // Board columns
    Route::post('/projects/{project}/columns', [BoardColumnController::class, 'store'])->name('columns.store');
    Route::patch('/projects/{project}/columns/reorder', [BoardColumnController::class, 'reorder'])->name('columns.reorder');
    Route::patch('/columns/{column}', [BoardColumnController::class, 'update'])->name('columns.update');
    Route::delete('/columns/{column}', [BoardColumnController::class, 'destroy'])->name('columns.destroy');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
