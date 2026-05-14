<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Primary user (Alex Rivera)
        $alex = User::create([
            'name'     => 'Alex Rivera',
            'email'    => 'alex@northstar.co',
            'password' => Hash::make('password'),
        ]);

        // Teammates
        $maya   = User::create(['name' => 'Maya Chen',   'email' => 'maya@northstar.co',   'password' => Hash::make('password')]);
        $devon  = User::create(['name' => 'Devon Park',  'email' => 'devon@northstar.co',  'password' => Hash::make('password')]);
        $priya  = User::create(['name' => 'Priya Shah',  'email' => 'priya@northstar.co',  'password' => Hash::make('password')]);
        $theo   = User::create(['name' => 'Theo Reyes',  'email' => 'theo@northstar.co',   'password' => Hash::make('password')]);
        $lin    = User::create(['name' => 'Lin Okafor',  'email' => 'lin@northstar.co',    'password' => Hash::make('password')]);
        $jamie  = User::create(['name' => 'Jamie Wu',    'email' => 'jamie@northstar.co',  'password' => Hash::make('password')]);

        // ── Project 1: Mobile App v3 ──────────────────────────────────────
        $mob = Project::create(['name' => 'Mobile App v3',    'key' => 'MOB', 'color' => '#4f46e5', 'owner_id' => $alex->id]);
        $web = Project::create(['name' => 'Marketing Site',   'key' => 'WEB', 'color' => '#0891b2', 'owner_id' => $alex->id]);
        $inf = Project::create(['name' => 'Platform & Infra', 'key' => 'INF', 'color' => '#16a34a', 'owner_id' => $maya->id]);
        $dsn = Project::create(['name' => 'Design System',    'key' => 'DSN', 'color' => '#7c3aed', 'owner_id' => $alex->id]);

        // Add members to Mobile App
        $mob->members()->attach([$maya->id, $devon->id, $priya->id, $theo->id, $lin->id, $jamie->id]);

        // Default columns for all projects
        foreach ([$mob, $web, $inf, $dsn] as $proj) {
            $pos = 0;
            foreach ([
                ['name' => 'Todo',        'color' => '#94948c'],
                ['name' => 'In Progress', 'color' => '#d97706'],
                ['name' => 'Review',      'color' => '#7c3aed'],
                ['name' => 'Done',        'color' => '#16a34a'],
            ] as $col) {
                $proj->boardColumns()->create([...$col, 'position' => $pos++]);
            }
        }

        $mobCols = $mob->boardColumns()->orderBy('position')->get()->keyBy('name');
        $todo    = $mobCols['Todo'];
        $inProg  = $mobCols['In Progress'];
        $review  = $mobCols['Review'];
        $done    = $mobCols['Done'];

        // Sprints
        $s23 = Sprint::create(['project_id' => $mob->id, 'name' => 'Sprint 23', 'start_date' => '2026-04-21', 'end_date' => '2026-05-05', 'status' => 'completed', 'locked' => true]);
        $s24 = Sprint::create(['project_id' => $mob->id, 'name' => 'Sprint 24', 'start_date' => '2026-05-05', 'end_date' => '2026-05-19', 'status' => 'active',    'locked' => false]);
        $s25 = Sprint::create(['project_id' => $mob->id, 'name' => 'Sprint 25', 'start_date' => '2026-05-19', 'end_date' => '2026-06-02', 'status' => 'planned',   'locked' => false]);

        // Default sprints for other projects
        Sprint::create(['project_id' => $web->id, 'name' => 'Sprint 9',  'start_date' => '2026-05-01', 'end_date' => '2026-05-15', 'status' => 'active']);
        Sprint::create(['project_id' => $inf->id, 'name' => 'Sprint 3',  'start_date' => '2026-04-28', 'end_date' => '2026-05-12', 'status' => 'active']);
        Sprint::create(['project_id' => $dsn->id, 'name' => 'Sprint 1',  'start_date' => '2026-05-05', 'end_date' => '2026-05-19', 'status' => 'active']);

        // ── Tasks for Sprint 24 ───────────────────────────────────────────
        $taskNum = 100;
        $makeKey = fn () => 'MOB-'.++$taskNum;

        $t1 = Task::create([
            'key' => $makeKey(), 'title' => 'Empty-state illustrations for onboarding screens',
            'description' => "We need 4 illustrations covering: empty inbox, no projects, no tasks assigned, and zero search results.\n\nMatch the existing illustration style (line + 1 accent color). Deliver SVGs + Figma source.",
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $todo->id, 'priority' => 'med',
            'assignee_id' => $priya->id, 'created_by' => $maya->id,
            'tags' => ['design', 'feature'], 'start_date' => '2026-05-06', 'due_date' => '2026-05-12',
        ]);

        $t2 = Task::create([
            'key' => $makeKey(), 'title' => 'Audit accessibility on settings flow',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $todo->id, 'priority' => 'high',
            'assignee_id' => $lin->id, 'created_by' => $alex->id,
            'tags' => ['a11y'], 'start_date' => '2026-05-05', 'due_date' => '2026-05-09',
        ]);

        $t3 = Task::create([
            'key' => $makeKey(), 'title' => 'Push notification opt-in copy review',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $todo->id, 'priority' => 'low',
            'assignee_id' => $theo->id, 'created_by' => $alex->id,
            'tags' => ['feature'],
        ]);

        $t4 = Task::create([
            'key' => $makeKey(), 'title' => 'Investigate flaky CI test on iOS build',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $todo->id, 'priority' => 'urgent',
            'assignee_id' => $devon->id, 'created_by' => $alex->id,
            'tags' => ['bug', 'infra'], 'start_date' => '2026-05-06', 'due_date' => '2026-05-07',
        ]);

        $t5 = Task::create([
            'key' => $makeKey(), 'title' => 'Refactor auth token refresh logic',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $inProg->id, 'priority' => 'high',
            'assignee_id' => $devon->id, 'created_by' => $maya->id,
            'tags' => ['backend'], 'start_date' => '2026-05-05', 'due_date' => '2026-05-14',
        ]);

        $t6 = Task::create([
            'key' => $makeKey(), 'title' => 'Animated splash screen – first-run only',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $inProg->id, 'priority' => 'med',
            'assignee_id' => $priya->id, 'created_by' => $alex->id,
            'tags' => ['frontend', 'design'], 'start_date' => '2026-05-08', 'due_date' => '2026-05-16',
        ]);

        $t7 = Task::create([
            'key' => $makeKey(), 'title' => 'Search debounce + cache layer',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $inProg->id, 'priority' => 'med',
            'assignee_id' => $jamie->id, 'created_by' => $maya->id,
            'tags' => ['frontend', 'perf'],
        ]);

        $t8 = Task::create([
            'key' => $makeKey(), 'title' => 'Localization keys cleanup pass',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $review->id, 'priority' => 'low',
            'assignee_id' => $theo->id, 'created_by' => $alex->id,
            'tags' => ['frontend'], 'due_date' => '2026-05-19',
        ]);

        $t9 = Task::create([
            'key' => $makeKey(), 'title' => 'API: project membership endpoints',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $review->id, 'priority' => 'high',
            'assignee_id' => $devon->id, 'created_by' => $maya->id,
            'tags' => ['backend'], 'due_date' => '2026-05-11',
        ]);

        $t10 = Task::create([
            'key' => $makeKey(), 'title' => 'Dark mode token audit',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $done->id, 'priority' => 'med',
            'assignee_id' => $lin->id, 'created_by' => $alex->id,
            'tags' => ['design'],
            'completed' => true, 'completed_at' => now()->subDay(), 'completed_by' => $lin->id,
        ]);

        $t11 = Task::create([
            'key' => $makeKey(), 'title' => 'Crash on cold-start (Android 14)',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $done->id, 'priority' => 'urgent',
            'assignee_id' => $devon->id, 'created_by' => $devon->id,
            'tags' => ['bug'],
            'completed' => true, 'completed_at' => now()->subDays(2), 'completed_by' => $devon->id,
        ]);

        // Not-yet-completed task in Done (triggers "awaiting completion" dashboard widget)
        $t12 = Task::create([
            'key' => $makeKey(), 'title' => 'Set up error monitoring dashboards',
            'project_id' => $mob->id, 'sprint_id' => $s24->id,
            'board_column_id' => $done->id, 'priority' => 'med',
            'assignee_id' => $jamie->id, 'created_by' => $alex->id,
            'tags' => ['infra'], 'completed' => false,
        ]);

        // ── Comments on t1 ───────────────────────────────────────────────
        $c1 = TaskComment::create(['task_id' => $t1->id, 'user_id' => $maya->id,  'body' => "@Priya – let's keep these vector so they scale well on tablets too."]);
        $c1->replies()->create(['user_id' => $priya->id, 'body' => "Agreed. I'll deliver SVGs + a Figma source file."]);
        TaskComment::create(['task_id' => $t1->id, 'user_id' => $theo->id,  'body' => "Could we sneak in a 5th for 'no notifications'? Same pass."]);

        // ── Audit logs ───────────────────────────────────────────────────
        $logs = [
            ['user_id' => $alex->id,  'project_id' => $mob->id, 'action' => 'sprint.locked',    'meta' => ['sprint' => 'Sprint 23'], 'created_at' => now()->subDay()->setTime(11, 2)],
            ['user_id' => $lin->id,   'project_id' => $mob->id, 'task_id' => $t10->id, 'action' => 'task.completed', 'meta' => [], 'created_at' => now()->subDay()->setTime(9, 15)],
            ['user_id' => $devon->id, 'project_id' => $mob->id, 'task_id' => $t9->id,  'action' => 'task.moved',     'meta' => ['column' => 'Review'], 'created_at' => now()->subDays(2)->setTime(16, 48)],
            ['user_id' => $alex->id,  'project_id' => $dsn->id, 'action' => 'project.created',  'meta' => ['name' => 'Design System'], 'created_at' => now()->subDays(2)->setTime(14, 30)],
            ['user_id' => $devon->id, 'project_id' => $mob->id, 'task_id' => $t11->id, 'action' => 'task.completed', 'meta' => [], 'created_at' => now()->subDays(3)->setTime(9, 0)],
            ['user_id' => $alex->id,  'project_id' => $mob->id, 'action' => 'sprint.created',   'meta' => ['sprint' => 'Sprint 24'], 'created_at' => now()->subDays(5)->setTime(10, 15)],
        ];

        foreach ($logs as $log) {
            AuditLog::create($log);
        }
    }
}

